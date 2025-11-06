<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\Weekday;
use App\Repositories\Interfaces\ReferralRepositoryInterface;
use App\Repositories\Interfaces\WeekdayRepositoryInterface;

class ReferralService
{
    protected ReferralRepositoryInterface $referralRepository;

    public function __construct(
        ReferralRepositoryInterface $referralRepository,
    )
    {
        $this->referralRepository = $referralRepository;
    }

    public function getAll()
    {
        return $this->referralRepository->all();
    }

    public function getById($id)
    {
        return $this->referralRepository->find($id);
    }

    public function getByName($name): ?Referral
    {
        return $this->referralRepository->findByName($name);
    }
}

