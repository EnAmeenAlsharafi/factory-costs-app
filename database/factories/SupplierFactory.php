<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'supplier_code' => strtoupper($this->faker->unique()->lexify('SUP-???')),
            'name' => 'مورد '.$this->faker->company(),
            'commercial_name' => $this->faker->company().' Co.',
            'contact_person' => $this->faker->name(),
            'phone' => '05'.$this->faker->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'city' => 'الرياض',
            'is_active' => true,
        ];
    }
}
