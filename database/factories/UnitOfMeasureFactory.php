<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('UOM-???')),
            'name_ar' => 'وحدة '.$this->faker->word(),
            'name_en' => $this->faker->word().' Unit',
            'symbol' => $this->faker->lexify('??'),
            'unit_type' => 'COUNT',
            'allows_decimal' => true,
            'decimal_precision' => 2,
            'is_active' => true,
        ];
    }
}
