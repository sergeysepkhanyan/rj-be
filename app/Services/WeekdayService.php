<?php

namespace App\Services;

use App\Mail\AdminAccessEmail;
use App\Models\Weekday;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\UserRoleRepositoryInterface;
use App\Repositories\Interfaces\WeekdayRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

