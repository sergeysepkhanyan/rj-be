<?php

namespace App\Services;

use App\Models\Weekday;
use App\Repositories\Interfaces\WeekdayRepositoryInterface;

class WeekdayService
{
    protected WeekdayRepositoryInterface $weekdayRepository;

    public function __construct(
        WeekdayRepositoryInterface $weekdayRepository,
    )
    {
        $this->weekdayRepository = $weekdayRepository;
    }

    public function getAllDays()
    {
        return $this->weekdayRepository->all();
    }

    public function getDayById($id)
    {
        return $this->weekdayRepository->find($id);
    }

    public function getDayByDay($day): ?Weekday
    {
        return $this->weekdayRepository->findByDay($day);
    }

    public function getDayByName($name): ?Weekday
    {
        return $this->weekdayRepository->findByName($name);
    }
}

