<?php

namespace Database\Seeders;

use App\Models\ProductionRouting;
use App\Models\ProductionRoutingOperation;
use App\Models\WorkCenter;
use Illuminate\Database\Seeder;

class StorageBedRoutingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $routing = ProductionRouting::updateOrCreate(
            ['routing_code' => 'RTG-000002'],
            [
                'name_ar' => 'مسار تصنيع أسرّة التخزين (فرعان متوازيان)',
                'description' => 'مسار مخصص لأسرّة التخزين: فرع الظهر والحواجز (نجارة -> سفنجة -> تنجيد) وفرع الهيدروليك المستقل (تركيب قاعدة التخزين)، يلتقيان عند تركيب الحواجز على قاعدة الهيدروليك، ثم التغليف. لا يمر هذا المسار بقسمي البوكسات أو التجميع العام.',
                'is_active' => true,
            ]
        );

        $wcCarpentry = WorkCenter::where('code', 'WC_CARPENTRY')->first();
        $wcFoam = WorkCenter::where('code', 'WC_FOAM')->first();
        $wcUpholstery = WorkCenter::where('code', 'WC_UPHOLSTERY')->first();
        $wcHydraulic = WorkCenter::where('code', 'WC_HYDRAULIC')->first();
        $wcPackaging = WorkCenter::where('code', 'WC_PACKAGING')->first();

        if (! $wcCarpentry || ! $wcHydraulic || ! $wcPackaging) {
            return;
        }

        // 1. Branch A: Headboard & Sides (identical to the default routing)
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
                'name_ar' => 'خياطة وتنجيد الظهر وحواجز السرير',
                'sequence_number' => 30,
                'branch_key' => 'BRANCH_A_HEADBOARD',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        // 2. Branch C: Hydraulic storage base (independent, parallel with Carpentry)
        $opHydraulicBase = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_HYDRAULIC_BASE'],
            [
                'work_center_id' => $wcHydraulic->id,
                'name_ar' => 'تركيب قاعدة آلية التخزين الهيدروليكية',
                'sequence_number' => 10,
                'branch_key' => 'BRANCH_C_HYDRAULIC',
                'is_parallel' => true,
                'is_required' => true,
            ]
        );

        // 3. Convergence: Hydraulic department attaches the upholstered rails to its base
        $opHydraulicAssembly = ProductionRoutingOperation::updateOrCreate(
            ['production_routing_id' => $routing->id, 'operation_code' => 'OP_HYDRAULIC_ASSEMBLY'],
            [
                'work_center_id' => $wcHydraulic->id,
                'name_ar' => 'تركيب حواجز السرير المنجدة على قاعدة التخزين',
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

        // Hydraulic assembly depends on Upholstery AND the Hydraulic base (Join node)
        $opHydraulicAssembly->dependencies()->syncWithoutDetaching([$opUpholstery->id, $opHydraulicBase->id]);

        // Packaging depends on Hydraulic assembly
        $opPackaging->dependencies()->syncWithoutDetaching([$opHydraulicAssembly->id]);
    }
}
