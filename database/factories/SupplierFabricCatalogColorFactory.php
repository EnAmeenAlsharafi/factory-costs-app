<?php

namespace Database\Factories;

use App\Models\FabricColor;
use App\Models\SupplierFabricCatalog;
use App\Models\SupplierFabricCatalogColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierFabricCatalogColor>
 */
class SupplierFabricCatalogColorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_fabric_catalog_id' => SupplierFabricCatalog::factory(),
            'fabric_color_id' => FabricColor::factory(),
            'supplier_color_code' => strtoupper(fake()->unique()->bothify('CLR-###')),
            'supplier_color_name' => null,
            'is_available' => true,
            'notes' => null,
        ];
    }
}
