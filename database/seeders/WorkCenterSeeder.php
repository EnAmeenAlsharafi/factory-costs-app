<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\WorkCenter;
use Illuminate\Database\Seeder;

class WorkCenterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $map = [
            'CARPENTRY' => ['code' => 'WC_CARPENTRY', 'name_ar' => 'ورشة النجارة والتأطير', 'sort_order' => 10],
            'FOAM' => ['code' => 'WC_FOAM', 'name_ar' => 'ورشة قص وتركيب الإسفنج', 'sort_order' => 20],
            'UPHOLSTERY' => ['code' => 'WC_UPHOLSTERY', 'name_ar' => 'ورشة الخياطة والتنجيد', 'sort_order' => 30],
            'BOX_PRODUCTION' => ['code' => 'WC_BOX_PROD', 'name_ar' => 'خط تصنيع البوكسات القواعد', 'sort_order' => 40],
            'BOX_PREPARATION' => ['code' => 'WC_BOX_PREP', 'name_ar' => 'خط تجهيز وتلبيس البوكسات', 'sort_order' => 50],
            'ASSEMBLY' => ['code' => 'WC_ASSEMBLY', 'name_ar' => 'محطة التجميع النهائي', 'sort_order' => 60],
            'PACKAGING' => ['code' => 'WC_PACKAGING', 'name_ar' => 'محطة الفحص والتغليف', 'sort_order' => 70],
        ];

        foreach ($map as $deptCode => $wcData) {
            $dept = Department::where('code', $deptCode)->first();
            if ($dept) {
                WorkCenter::updateOrCreate(
                    ['code' => $wcData['code']],
                    [
                        'department_id' => $dept->id,
                        'name_ar' => $wcData['name_ar'],
                        'description' => "مركز عمل تصنيعي تابع لقسم {$dept->name_ar}",
                        'is_active' => true,
                        'sort_order' => $wcData['sort_order'],
                        'allows_parallel_work' => true,
                    ]
                );
            }
        }
    }
}
