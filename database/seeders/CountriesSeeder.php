<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CountriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Fetching countries from REST Countries API...');

        try {
            $response = Http::timeout(30)->get('https://restcountries.com/v3.1/all?fields=name,cca2,cca3,translations');

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch countries from API. Status: ' . $response->status());
            }

            $countries = $response->json();

            if (empty($countries)) {
                throw new \Exception('No countries returned from API');
            }

            $this->command->info('Found ' . count($countries) . ' countries. Inserting into database...');

            $sortOrder = 0;
            $inserted = 0;
            $updated = 0;

            foreach ($countries as $countryData) {
                $name = $countryData['name']['common'] ?? null;
                $code = $countryData['cca2'] ?? null;
                $code3 = $countryData['cca3'] ?? null;
                $nameAr = $countryData['translations']['ara']['common'] ?? $countryData['translations']['ar']['common'] ?? null;

                if (!$name || !$code) {
                    continue;
                }

                $country = Country::updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'name_ar' => $nameAr,
                        'code' => $code,
                        'code3' => $code3,
                        'enabled' => true,
                        'sort_order' => $sortOrder++,
                    ]
                );

                if ($country->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }
            }

            $this->command->info("Countries seeded successfully! Inserted: {$inserted}, Updated: {$updated}");

            $this->command->info('Setting priority countries (UAE, Saudi Arabia, etc.)...');
            $this->setPriorityCountries();

        } catch (\Exception $e) {
            $this->command->error('Error fetching countries: ' . $e->getMessage());
            $this->command->warn('Falling back to manual country list...');
            $this->seedManualCountries();
        }
    }

    /**
     * Set priority countries with lower sort_order
     */
    private function setPriorityCountries(): void
    {
        $priorityCountries = [
            'AE' => ['name' => 'United Arab Emirates', 'sort_order' => 1],
            'SA' => ['name' => 'Saudi Arabia', 'sort_order' => 2],
            'KW' => ['name' => 'Kuwait', 'sort_order' => 3],
            'QA' => ['name' => 'Qatar', 'sort_order' => 4],
            'BH' => ['name' => 'Bahrain', 'sort_order' => 5],
            'OM' => ['name' => 'Oman', 'sort_order' => 6],
        ];

        foreach ($priorityCountries as $code => $data) {
            Country::where('code', $code)->update([
                'sort_order' => $data['sort_order'],
            ]);
        }
    }

    /**
     * Fallback: Seed manual list if API fails
     */
    private function seedManualCountries(): void
    {
        $countries = [
            ['name' => 'United Arab Emirates', 'name_ar' => 'الإمارات العربية المتحدة', 'code' => 'AE', 'code3' => 'ARE', 'sort_order' => 1],
            ['name' => 'Saudi Arabia', 'name_ar' => 'المملكة العربية السعودية', 'code' => 'SA', 'code3' => 'SAU', 'sort_order' => 2],
            ['name' => 'Kuwait', 'name_ar' => 'الكويت', 'code' => 'KW', 'code3' => 'KWT', 'sort_order' => 3],
            ['name' => 'Qatar', 'name_ar' => 'قطر', 'code' => 'QA', 'code3' => 'QAT', 'sort_order' => 4],
            ['name' => 'Bahrain', 'name_ar' => 'البحرين', 'code' => 'BH', 'code3' => 'BHR', 'sort_order' => 5],
            ['name' => 'Oman', 'name_ar' => 'عُمان', 'code' => 'OM', 'code3' => 'OMN', 'sort_order' => 6],
            ['name' => 'Egypt', 'name_ar' => 'مصر', 'code' => 'EG', 'code3' => 'EGY', 'sort_order' => 10],
            ['name' => 'Jordan', 'name_ar' => 'الأردن', 'code' => 'JO', 'code3' => 'JOR', 'sort_order' => 11],
            ['name' => 'Lebanon', 'name_ar' => 'لبنان', 'code' => 'LB', 'code3' => 'LBN', 'sort_order' => 12],
            ['name' => 'United States', 'name_ar' => 'الولايات المتحدة', 'code' => 'US', 'code3' => 'USA', 'sort_order' => 20],
            ['name' => 'United Kingdom', 'name_ar' => 'المملكة المتحدة', 'code' => 'GB', 'code3' => 'GBR', 'sort_order' => 21],
            ['name' => 'Canada', 'name_ar' => 'كندا', 'code' => 'CA', 'code3' => 'CAN', 'sort_order' => 22],
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(
                ['code' => $countryData['code']],
                array_merge($countryData, ['enabled' => true])
            );
        }

        $this->command->info('Manual countries seeded successfully!');
    }
}
