<?php

namespace Database\Factories;

use App\Models\ManufacturingTemplate;
use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManufacturingTemplate>
 */
class ManufacturingTemplateFactory extends Factory
{
    protected $model = ManufacturingTemplate::class;

    public function definition(): array
    {
        return [
            'template_code' => app(DocumentNumberService::class)->generateTemplateCode(),
            'name_ar' => 'قالب '.$this->faker->word(),
            'name_en' => 'Manufacturing Template',
            'description' => 'قالب تصنيع مسبق الإعداد',
            'is_active' => true,
        ];
    }
}
