<?php

namespace Database\Seeders;

use App\Models\Weekday;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;

class WorkingHoursSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'monday'    => ['open' => true],
            'tuesday'   => ['open' => true],
            'wednesday' => ['open' => true],
            'thursday'  => ['open' => true],
            'friday'    => ['open' => true],
            'saturday'  => ['open' => true],
            'sunday'    => ['open' => false],
        ];

        foreach ($defaults as $dayName => $config) {
            $weekday = Weekday::query()
                ->whereRaw('LOWER(name) = ?', [$dayName])
                ->first();

            if (! $weekday) {
                continue;
            }

            WorkingHour::updateOrCreate(
                ['weekday_id' => $weekday->id],
                [
                    'is_closed' => ! $config['open'],
                    'start_time' => $config['open'] ? '07:00' : null,
                    'end_time'   => $config['open'] ? '23:00' : null,
                    'break_start_time' => null,
                    'break_end_time'   => null,
                ]
            );
        }
    }
}

