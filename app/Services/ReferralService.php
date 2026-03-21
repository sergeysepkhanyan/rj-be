<?php

namespace App\Services;

use App\Models\Referral;
use App\Repositories\Interfaces\ReferralRepositoryInterface;

class ReferralService
{

    public function __construct(
      protected  ReferralRepositoryInterface $referralRepository,
    ){}

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

    public function create(array $data): Referral
    {
        return $this->referralRepository->create($data);
    }

    public function update(int $id, array $data): Referral
    {
        $referral = $this->referralRepository->find($id);
        return $this->referralRepository->update($referral, $data);
    }

    public function delete(int $id): void
    {
        $referral = $this->referralRepository->find($id);
        $this->referralRepository->delete($referral);
    }
}
