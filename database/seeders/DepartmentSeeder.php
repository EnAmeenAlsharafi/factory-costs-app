<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'CARPENTRY',
                'name_ar' => 'النجارة',
                'name_en' => 'Carpentry & Woodworking',
                'description' => 'قسم تفصيل وقص وتجميع الهياكل الخشبية للأسرة والظهور.',
                'sort_order' => 10,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'FOAM',
                'name_ar' => 'السفنجة',
                'name_en' => 'Foam Application',
                'description' => 'قسم تركيب وقص الإسفنج بمختلف الكثافات والسمكات على الهياكل.',
                'sort_order' => 20,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'UPHOLSTERY',
                'name_ar' => 'التنجيد',
                'name_en' => 'Upholstery',
                'description' => 'قسم خياطة وتلبيس الأقمشة وتنجيد ظهور وقواعد الأسرة.',
                'sort_order' => 30,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'BOX_PRODUCTION',
                'name_ar' => 'البوكسات',
                'name_en' => 'Box / Base Production',
                'description' => 'خط إنتاج متوازي لتصنيع القواعد والبوكسات القياسية المشاعة.',
                'sort_order' => 40,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'BOX_PREPARATION',
                'name_ar' => 'تلبيس وتجهيز البوكسات',
                'name_en' => 'Box Preparation & Covering',
                'description' => 'تجهيز وتلبيس القواعد المشاعة وتهيئتها للتجميع النهائي.',
                'sort_order' => 50,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'ASSEMBLY',
                'name_ar' => 'التجميع',
                'name_en' => 'Assembly',
                'description' => 'تجميع أجزاء السرير (الظهر، القواعد، السحارات والمفصلات).',
                'sort_order' => 60,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'PACKAGING',
                'name_ar' => 'التغليف',
                'name_en' => 'Packaging & Inspection',
                'description' => 'الفحص النهائي الظاهري لجودة المنتج والتغليف والتحضير للتحميل.',
                'sort_order' => 70,
                'is_production_department' => true,
                'is_active' => true,
            ],
            [
                'code' => 'WAREHOUSE',
                'name_ar' => 'المستودع',
                'name_en' => 'Warehouse',
                'description' => 'مستودع المواد الخام، استلام التوريدات، وتتبع اللوت وصرف الخامات.',
                'sort_order' => 80,
                'is_production_department' => false,
                'is_active' => true,
            ],
            [
                'code' => 'DELIVERY_INSTALLATION',
                'name_ar' => 'التوصيل والتركيب',
                'name_en' => 'Delivery & Installation',
                'description' => 'فريق التوصيل الميداني للمصنع والتركيب لدى العملاء والمعارض.',
                'sort_order' => 90,
                'is_production_department' => false,
                'is_active' => true,
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                $dept
            );
        }
    }
}
