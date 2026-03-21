<?php

namespace App\Repositories\Interfaces;

use App\Models\Referral;

interface ReferralRepositoryInterface
{
    public function all();
    public function find($id);
    public function findByName(string $name): ?Referral;
    public function create(array $data): Referral;
    public function update(Referral $referral, array $data): Referral;
    public function delete(Referral $referral): void;
}
