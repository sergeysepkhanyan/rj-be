<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Page;

class PagesSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'homepage' => [
                'hero' => [
                    'image' => '',
                    'title' => '',
                    'description' => '',
                    'buttonText' => '',
                    'buttonLink' => '',
                    'bannerTexts' => [
                        ''
                    ]
                ],
                'appointmentImages' => [
                    [
                        'image' => '',
                        'label' => ''
                    ]
                ],
                'partners' => [
                    [
                        'id' => '',
                        'name' => '',
                        'image' => ''
                    ]
                ]
            ],
            'reviews' => [
                [
                    'id' => '',
                    'name' => '',
                    'image' => '',
                    'description' => '',
                    'rating' => 5,
                    'date' => ''
                ]
            ],
            'footer' => [
                'images' => [
                    [
                        'label' => '',
                        'src' => ''
                    ]
                ],
                'info' => [
                    'title' => '',
                    'desc' => ''
                ],
                'backgroundImage' => ''
            ],
            'contact' => [
                'socials' => [
                    [
                        'label' => '',
                        'href' => ''
                    ]
                ],
                'phoneNumber' => '',
                'email' => '',
                'address' => '',
                'googleMapLink' => ''
            ]
        ];

        foreach ($data as $slug => $content) {
            Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title'   => ucfirst($slug),
                    'content' => $content,
                    'url'     => null,
                ]
            );
        }
    }
}
