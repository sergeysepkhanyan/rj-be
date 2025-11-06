<?php

namespace Database\Seeders;

use App\Models\Referral;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReferralsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $referrals = [
            'bronze' => [
                'name' => 'Bronze',
                'value' => 10,
                'type' => 'percentage'
            ],
            'silver' => [
                'name' => 'Silver',
                'value' => 15,
                'type' => 'percentage'
            ],
            'gold' => [
                'name' => 'Gold',
                'value' => 20,
                'type' => 'percentage'
            ]
        ];

        foreach ($referrals as $referral) {
            Referral::firstOrCreate(
                ['name' => $referral['name']],
                ['value' => $referral['value'], 'type' => $referral['type']]
            );
        }
    }
}
