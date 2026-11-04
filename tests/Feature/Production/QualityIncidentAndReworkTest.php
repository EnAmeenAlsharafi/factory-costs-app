<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\ProductionReworkAction;
use App\Models\ProductionWasteReason;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\WorkCenter;
use App\Services\QualityIncidentService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityIncidentAndReworkTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $productionOrder;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();
        $this->department = Department::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000202',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000202',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 1,
            'released_quantity' => 1,
            'status' => 'IN_PROGRESS',
        ]);
    }

    public function test_can_create_quality_incident_and_set_disposition(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.quality-incidents.store'), [
            'production_order_id' => $this->productionOrder->id,
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'responsible_department_id' => $this->department->id,
            'incident_type' => 'WRONG_DIMENSION',
            'description' => 'خطأ في أبعاد التقطيع بالمشغل',
            'severity' => 'HIGH',
            'disposition' => 'REWORK',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('quality_incidents', 1);

        $incident = QualityIncident::first();
        $this->assertEquals('ACTION_REQUIRED', $incident->status);
        $this->assertEquals('REWORK', $incident->disposition);

        // Automatic Rework Action generated
        $this->assertDatabaseCount('production_rework_actions', 1);
        $rework = ProductionReworkAction::first();
        $this->assertEquals('PENDING', $rework->status);
        $this->assertEquals('REWORK', $rework->action_type);
    }

    public function test_completing_rework_action_resolves_quality_incident(): void
    {
        $service = app(QualityIncidentService::class);

        $incident = $service->createIncident($this->productionOrder, $this->manager, [
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'responsible_department_id' => $this->department->id,
            'incident_type' => 'WORKMANSHIP_DEFECT',
            'description' => 'عيب تنجيد محلي',
            'severity' => 'MEDIUM',
            'disposition' => 'REPAIR',
        ]);

        $rework = $incident->reworkActions->first();
        $this->assertNotNull($rework);

        $service->completeReworkAction($rework, $this->manager);

        $this->assertEquals('COMPLETED', $rework->fresh()->status);
        $this->assertEquals('RESOLVED', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->resolved_at);
    }

    public function test_worker_cannot_set_disposition_when_creating_incident(): void
    {
        $workerRole = Role::where('name', 'production_worker')->first();
        $worker = User::factory()->create(['role_id' => $workerRole->id, 'is_active' => true, 'department_id' => $this->department->id]);

        $response = $this->actingAs($worker)->post(route('production.quality-incidents.store'), [
            'production_order_id' => $this->productionOrder->id,
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'incident_type' => 'WRONG_DIMENSION',
            'description' => 'خطأ اكتشفه النجار',
            'severity' => 'HIGH',
            'disposition' => 'ACCEPT_AS_IS',
        ]);

        $response->assertSessionHasErrors('disposition');
        $this->assertDatabaseCount('quality_incidents', 0);
    }

    public function test_cannot_create_duplicate_open_incident_for_same_operation_and_type(): void
    {
        $service = app(QualityIncidentService::class);
        $operation = ProductionOrderOperation::create([
            'production_order_id' => $this->productionOrder->id,
            'work_center_id' => WorkCenter::first()->id,
            'operation_code' => 'OP_CARPENTRY',
            'operation_name_snapshot' => 'النجارة',
            'sequence_number' => 1,
            'required_quantity' => 1,
            'status' => 'IN_PROGRESS',
        ]);

        $service->createIncident($this->productionOrder, $this->manager, [
            'production_order_operation_id' => $operation->id,
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'incident_type' => 'WRONG_FABRIC',
            'description' => 'قماش خاطئ - اكتشاف أول',
            'severity' => 'MEDIUM',
        ]);

        $this->expectException(Exception::class);

        $service->createIncident($this->productionOrder, $this->manager, [
            'production_order_operation_id' => $operation->id,
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'incident_type' => 'WRONG_FABRIC',
            'description' => 'نفس العيب - اكتشاف ثانٍ من قسم آخر',
            'severity' => 'MEDIUM',
        ]);
    }

    public function test_recording_waste_linked_to_scrap_incident_resolves_it(): void
    {
        $service = app(QualityIncidentService::class);

        $incident = $service->createIncident($this->productionOrder, $this->manager, [
            'affected_quantity' => 1,
            'detected_department_id' => $this->department->id,
            'incident_type' => 'MATERIAL_DAMAGE',
            'description' => 'تلف كامل يستوجب الإتلاف',
            'severity' => 'HIGH',
            'disposition' => 'SCRAP',
        ]);

        $this->assertEquals('SCRAP', $incident->disposition);
        $this->assertEquals('ACTION_REQUIRED', $incident->status);

        $category = MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true]);
        $unit = UnitOfMeasure::first();
        $material = Material::create([
            'code' => 'MAT-TEST-SCRAP-999',
            'name_ar' => 'مادة اختبار الإتلاف',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->manager)->post(route('production.waste.store'), [
            'production_order_id' => $this->productionOrder->id,
            'quality_incident_id' => $incident->id,
            'material_id' => $material->id,
            'quantity' => 1,
            'unit_id' => $material->base_unit_id,
            'waste_reason_id' => ProductionWasteReason::first()->id,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('production.waste.index'));
        $this->assertEquals('RESOLVED', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->resolved_at);
    }
}
