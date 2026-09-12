<?php

namespace Database\Seeders;

use App\Models\ProductionRouting;
use App\Models\ProductionRoutingOperation;
use App\Models\WorkCenter;
use Illuminate\Database\Seeder;

class DefaultBedRoutingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $routing = ProductionRouting::updateOrCreate(
            ['routing_code' => 'RTG-000001'],
            [
                'name_ar' => 'مسار تصنيع الأسرة القياسي (فرعان متوازيان)',
                'description' => 'مسار افتراضي يتضمن فرع الظهر والجانبيات (نجارة -> سفنجة -> تنجيد) وفرع البوكسات (تصنيع بوكسات -> تلبيس وتجهيز) ويلتقيان عند التجميع ثم التغليف.',
                'is_active' => true,
            ]
        );

        $wcCarpentry = WorkCenter::where('code', 'WC_CARPENTRY')->first();
        $wcFoam = WorkCenter::where('code', 'WC_FOAM')->first();
        $wcUpholstery = WorkCenter::where('code', 'WC_UPHOLSTERY')->first();
        $wcBoxProd = WorkCenter::where('code', 'WC_BOX_PROD')->first();
        $wcBoxPrep = WorkCenter::where('code', 'WC_BOX_PREP')->first();
        $wcAssembly = WorkCenter::where('code', 'WC_ASSEMBLY')->first();
        $wcPackaging = WorkCenter::where('code', 'WC_PACKAGING')->first();

        if (! $wcCarpentry || ! $wcPackaging) {
            return;
        }

        // 1. Branch A: Headboard & Sides
        $opCarpentry = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_CARPENTRY'],
            [
                'work_center_id' => $wcCarpentry->id,
                'name_ar' => 'قص وتأطير هيكل الخشب',
                'sequence_number' => 10,
                'branch_key' => 'BRANCH_A_HEADBOARD',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        $opFoam = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_FOAM'],
            [
                'work_center_id' => $wcFoam->id,
                'name_ar' => 'قص وتلبيس الإسفنج',
                'sequence_number' => 20,
                'branch_key' => 'BRANCH_A_HEADBOARD',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        $opUpholstery = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_UPHOLSTERY'],
            [
                'work_center_id' => $wcUpholstery->id,
                'name_ar' => 'خياطة وتنجيد القماش',
                'sequence_number' => 30,
                'branch_key' => 'BRANCH_A_HEADBOARD',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        // 2. Branch B: Box & Bases
        $opBoxProd = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_BOX_PROD'],
            [
                'work_center_id' => $wcBoxProd->id,
                'name_ar' => 'تصنيع هيكل البوكس',
                'sequence_number' => 15,
                'branch_key' => 'BRANCH_B_BOX',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        $opBoxPrep = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_BOX_PREP'],
            [
                'work_center_id' => $wcBoxPrep->id,
                'name_ar' => 'تلبيس وتجهيز البوكسات',
                'sequence_number' => 25,
                'branch_key' => 'BRANCH_B_BOX',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        // 3. Convergence / Join Operations
        $opAssembly = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_ASSEMBLY'],
            [
                'work_center_id' => $wcAssembly->id,
                'name_ar' => 'تجميع الظهر والقواعد والسحارات',
                'sequence_number' => 40,
                'branch_key' => 'MAIN',
                'is_parallel' => false,
                'is_required' => true,
            ]
        );

        $opPackaging = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_PACKAGING'],
            [
                'work_center_id' => $wcPackaging->id,
                'name_ar' => 'الفحص الجودة النهائي والتغليف',
                'sequence_number' => 50,
                'branch_key' => 'MAIN',
                'is_parallel' => false,
                'is_required' => true,
            ]
        );

        // Dependencies:
        // Foam depends on Carpentry
        $opFoam->dependencies()->syncWithoutDetaching([$opCarpentry->id]);
        // Upholstery depends on Foam
        $opUpholstery->dependencies()->syncWithoutDetaching([$opFoam->id]);

        // Box Prep depends on Box Prod
        $opBoxPrep->dependencies()->syncWithoutDetaching([$opBoxProd->id]);

        // Assembly depends on Upholstery AND Box Prep (Join node)
        $opAssembly->dependencies()->syncWithoutDetaching([$opUpholstery->id, $opBoxPrep->id]);

        // Packaging depends on Assembly
        $opPackaging->dependencies()->syncWithoutDetaching([$opAssembly->id]);
    }
}
