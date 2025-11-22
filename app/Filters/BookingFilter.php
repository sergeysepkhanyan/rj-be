<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BookingFilter
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

        if ($this->request->has('master_id')) {
            $this->filterByMaster();
        }

        if ($this->request->has('date')) {
            $this->filterByDate();
        }

        if ($this->request->has('search')) {
            $this->filterBySearch();
        }

        return $this->query;
    }

    protected function filterByMaster(): void
    {
        $this->query->where('master_id', $this->request->master_id);
    }

    protected function filterByDate(): void
    {
        $this->query->where('date', $this->request->date);
    }

    protected function filterBySearch(): void
    {
        $search = $this->request->search;
        $this->query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%");
        });
    }
}
