<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Department;
use App\Models\ProductionOrder;
use App\Models\ProductionReworkAction;
use App\Models\QualityIncident;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\QualityIncidentService;
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
}
