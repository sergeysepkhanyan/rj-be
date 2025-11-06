<?php

namespace App\Repositories;

use App\Models\Referral;
use App\Repositories\Interfaces\ReferralRepositoryInterface;

class ReferralRepository implements ReferralRepositoryInterface
{
    public function all()
    {
        return Referral::all();
    }

    public function find($id)
    {
        return Referral::findOrFail($id);
    }

    public function findByName(string $name): ?Referral
    {
        return Referral::where('name', $name)->first();
    }
}

