<?php

namespace App\Repositories;

use App\Models\Weekday;
use App\Repositories\Interfaces\WeekdayRepositoryInterface;

class WeekdayRepository implements WeekdayRepositoryInterface
{
    public function all()
    {
        return Weekday::all();
    }

    public function find($id)
    {
        return Weekday::findOrFail($id);
    }

    public function findByDay(int $day): ?Weekday
    {
        return Weekday::where('day', $day)->first();
    }

    public function findByName(string $name): ?Weekday
    {
        return Weekday::where('name', $name)->first();
    }
}

