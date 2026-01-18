<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductFilter
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

        if ($this->request->has('availability')) {
            $this->filterByAvailability();
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

        return $this->query;
    }

    protected function filterByAvailability(): void
    {
        $raw = strtolower(trim((string) $this->request->availability));
        $normalized = match ($raw) {
            'on stock', 'on_stock', 'instock', 'in stock', 'available', '1', 'true' => 'on_stock',
            'out', 'out_of_stock', 'out of stock', '0', 'false' => 'out',
            default => null,
        };

        if ($normalized === 'on_stock') {
            $this->query->where('max_quantity', '>', 0);
        } elseif ($normalized === 'out') {
            $this->query->where('max_quantity', '<=', 0);
        }
    }

    protected function filterByStatus(): void
    {
        $status = strtolower(trim((string) $this->request->status));
        if (in_array($status, ['draft', 'active'], true)) {
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
