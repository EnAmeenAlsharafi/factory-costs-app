<?php

namespace Database\Factories;

use App\Models\SemiFinishedComponent;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SemiFinishedComponent>
 */
class SemiFinishedComponentFactory extends Factory
{
    protected $model = SemiFinishedComponent::class;

    public function definition(): array
    {
        return [
            'component_code' => app(DocumentNumberService::class)->generateComponentCode(),
            'name_ar' => 'بوكس سرير '.$this->faker->numberBetween(140, 200).'×200 سم',
            'name_en' => 'Bed Box Component',
            'width_cm' => 160.00,
            'length_cm' => 200.00,
            'has_storage' => $this->faker->boolean(),
            'is_active' => true,
        ];
    }
}
