<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Supplier;
use App\Models\SupplierFabricCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierFabricCatalog>
 */
class SupplierFabricCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'supplier_id' => Supplier::factory(),
            'catalog_number' => strtoupper(fake()->unique()->bothify('CAT-####')),
            'catalog_name' => fake()->words(2, true),
            'supplier_material_code' => strtoupper(fake()->bothify('FAB-###')),
            'image_path' => null,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
