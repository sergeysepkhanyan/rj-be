<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Weekday;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WeekDaysSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
       $days = [
           'Monday',
           'Tuesday',
           'Wednesday',
           'Thursday',
           'Friday',
           'Saturday',
           'Sunday'
       ];
       foreach ($days as $day => $name) {
           $day += 1;
           Weekday::firstOrCreate(
               ['day' => $day],
               ['name' => $name]
           );
       }
    }
}
