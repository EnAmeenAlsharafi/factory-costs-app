<?php

namespace Database\Seeders;

use App\Models\MaterialCategory;
use Illuminate\Database\Seeder;

class MaterialCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'WOOD',
                'name_ar' => 'الخشب',
                'name_en' => 'Wood & Timber',
                'description' => 'ألواح الخشب MDF، الخشب السويدي، والكونتر المستخدمة في هياكل الظهور والبوكسات.',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'FOAM',
                'name_ar' => 'الإسفنج',
                'name_en' => 'Foam & Padding',
                'description' => 'أفرخ ورولات الإسفنج والشرائح بمختلف الكثافات والسمكات.',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'FABRIC',
                'name_ar' => 'القماش',
                'name_en' => 'Fabrics & Upholstery',
                'description' => 'أقمشة التنجيد بالمتر الطولي (مخمل، كتان، جلد صناعي، كتاكت) برولات وألوان مختلفة.',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'ACCESSORY',
                'name_ar' => 'الإكسسوارات',
                'name_en' => 'Accessories & Hardware',
                'description' => 'المستلزمات المعدنية والبلاستيكية (أرجل الأسرة، المفصلات، السحارات، المسامير).',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => 'PACKAGING',
                'name_ar' => 'مواد التغليف',
                'name_en' => 'Packaging Materials',
                'description' => 'كراتين التغليف، رولات البابلز الشفاف، الشريط اللاصق، وحاميات الزوايا.',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'ADHESIVE',
                'name_ar' => 'المواد اللاصقة',
                'name_en' => 'Adhesives & Glues',
                'description' => 'غرية الإسفنج، غراء الخشب، والمواد الكيميائية اللاصقة المستخدمة في الإنتاج.',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'code' => 'CONSUMABLE',
                'name_ar' => 'المواد الاستهلاكية',
                'name_en' => 'Consumables & Tools',
                'description' => 'دبابيس التنجيد، شفرات القص، الخيوط، والمستلزمات التشغيلية المباشرة.',
                'sort_order' => 70,
                'is_active' => true,
            ],
            [
                'code' => 'OTHER',
                'name_ar' => 'أخرى',
                'name_en' => 'Other Materials',
                'description' => 'أي خامات ومواد أخرى غير مصنفة أعلاه.',
                'sort_order' => 80,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            MaterialCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }
    }
}
