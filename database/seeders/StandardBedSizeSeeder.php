<?php

namespace Database\Seeders;

use App\Models\StandardBedSize;
use Illuminate\Database\Seeder;

class StandardBedSizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            [
                'code' => 'SIZE-100X200',
                'width_cm' => 100.0,
                'length_cm' => 200.0,
                'name_ar' => '100 × 200 سم (مفرد)',
                'sort_order' => 1,
            ],
            [
                'code' => 'SIZE-120X200',
                'width_cm' => 120.0,
                'length_cm' => 200.0,
                'name_ar' => '120 × 200 سم (مفرد كبير)',
                'sort_order' => 2,
            ],
            [
                'code' => 'SIZE-140X200',
                'width_cm' => 140.0,
                'length_cm' => 200.0,
                'name_ar' => '140 × 200 سم (مزدوج صغير)',
                'sort_order' => 3,
            ],
            [
                'code' => 'SIZE-160X200',
                'width_cm' => 160.0,
                'length_cm' => 200.0,
                'name_ar' => '160 × 200 سم (مزدوج كوين)',
                'sort_order' => 4,
            ],
            [
                'code' => 'SIZE-180X200',
                'width_cm' => 180.0,
                'length_cm' => 200.0,
                'name_ar' => '180 × 200 سم (مزدوج كينج)',
                'sort_order' => 5,
            ],
            [
                'code' => 'SIZE-200X200',
                'width_cm' => 200.0,
                'length_cm' => 200.0,
                'name_ar' => '200 × 200 سم (مزدوج سوبر كينج)',
                'sort_order' => 6,
            ],
        ];

        foreach ($sizes as $size) {
            StandardBedSize::updateOrCreate(
                ['code' => $size['code']],
                [
                    'width_cm' => $size['width_cm'],
                    'length_cm' => $size['length_cm'],
                    'name_ar' => $size['name_ar'],
                    'sort_order' => $size['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
