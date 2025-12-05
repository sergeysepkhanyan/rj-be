<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ServiceFilter
{
    protected Request $request;
    protected Builder $query;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $query): Builder
    {
        $this->query = $query->with('subServices.items');

        if ($this->request->has('search')) {
            $this->filterBySearch();
        }

        return $this->query;
    }

    protected function filterBySearch(): void
    {
        $search = $this->request->search;

        $this->query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('subServices', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->orWhereHas('subServices.items', function ($item) use ($search) {
                    $item->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
        });
    }
}

