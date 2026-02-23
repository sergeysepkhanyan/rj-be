<?php

namespace App\Filters;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderFilter
{
    protected Request $request;
    protected Builder $query;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $query): Builder
    {
        $this->query = $query;

        if ($this->request->has('type')) {
            $this->filterByType();
        }

        if ($this->request->has('status')) {
            $this->filterByStatus();
        }

        if ($this->request->has('year')) {
            $this->filterByYear();
        }

        if ($this->request->has('month')) {
            $this->filterByMonth();
        }

        if ($this->request->has('search')) {
            $this->filterBySearch();
        }

        return $this->query;
    }

    protected function filterByType(): void
    {
        $type = strtolower(trim((string) $this->request->type));
        $allowed = ['product', 'booking'];
        if (in_array($type, $allowed, true)) {
            $this->query->where('type', $type);
        }
    }

    protected function filterByStatus(): void
    {
        $status = strtolower(trim((string) $this->request->status));
        $allowed = ['pending', 'pending_payment', 'processing', 'shipped', 'paid', 'refunded', 'cancelled', 'fulfilled'];
        if (in_array($status, $allowed, true)) {
            $this->query->where('status', $status);
        }
    }

    protected function filterByYear(): void
    {
        $year = (int) $this->request->year;
        if ($year > 0) {
            $this->query->whereYear('created_at', $year);
        }
    }

    protected function filterByMonth(): void
    {
        $month = $this->parseMonth($this->request->month);
        if ($month > 0 && $month <= 12) {
            $this->query->whereMonth('created_at', $month);
        }
    }

    protected function filterBySearch(): void
    {
        $search = trim((string) $this->request->search);
        if ($search === '') {
            return;
        }

        $this->query->where(function ($q) use ($search) {
            $q->where('reference', 'like', "%{$search}%")
                ->orWhereHas('items.product', function ($p) use ($search) {
                    $p->where('name', 'like', "%{$search}%");
                })
                ->orWhereHasMorph('orderable', [Booking::class], function ($b) use ($search) {
                    $b->whereHas('services.bookable', function ($s) use ($search) {
                        $s->where('name', 'like', "%{$search}%");
                    });
                });
        });
    }

    protected function parseMonth($value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $str = strtolower(trim((string) $value));
        if ($str === '') {
            return 0;
        }

        $months = [
            'january' => 1,
            'february' => 2,
            'march' => 3,
            'april' => 4,
            'may' => 5,
            'june' => 6,
            'july' => 7,
            'august' => 8,
            'september' => 9,
            'october' => 10,
            'november' => 11,
            'december' => 12,
        ];

        return $months[$str] ?? 0;
    }
}
