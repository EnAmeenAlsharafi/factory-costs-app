<?php

namespace Tests\Feature\Production;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionWasteTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected ProductionOrder $productionOrder;

    protected Material $material;

    protected ProductionWasteReason $wasteReason;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $customer = Customer::first();
        $salesChannel = SalesChannel::first();

        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000203',
            'customer_id' => $customer->id,
            'sales_channel_id' => $salesChannel->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1500.00,
            'created_by_user_id' => $this->manager->id,
        ]);

        $category = MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true]);
        $unit = UnitOfMeasure::first();
        $this->material = Material::create([
            'code' => 'MAT-TEST-WASTE-999',
            'name_ar' => 'خشب سويدي 2×4 اختبار',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::first();
        InventoryLot::create([
            'lot_code' => 'LOT-WASTE-TEST',
            'material_id' => $this->material->id,
            'warehouse_id' => $warehouse->id,
            'received_date' => now(),
            'original_quantity' => 50,
            'remaining_quantity' => 50,
            'base_unit_id' => $unit->id,
            'unit_cost' => 25.00,
            'status' => 'ACTIVE',
        ]);

        $orderLine = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
        ]);

        $this->productionOrder = ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000203',
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $orderLine->id,
            'ordered_quantity' => 1,
            'released_quantity' => 1,
            'status' => 'IN_PROGRESS',
        ]);

        $this->wasteReason = ProductionWasteReason::first();
    }

    public function test_can_create_production_waste_record(): void
    {
        $response = $this->actingAs($this->manager)->post(route('production.waste.store'), [
            'production_order_id' => $this->productionOrder->id,
            'material_id' => $this->material->id,
            'quantity' => 4,
            'unit_id' => $this->material->base_unit_id,
            'waste_reason_id' => $this->wasteReason->id,
            'occurred_at' => now()->format('Y-m-d\TH:i'),
            'notes' => 'تلف أثناء القص والتقطيع بالنجارة',
        ]);

        $response->assertRedirect(route('production.waste.index'));
        $this->assertDatabaseCount('production_waste_records', 1);

        $waste = ProductionWasteRecord::first();
        $this->assertEquals(4, (float) $waste->quantity);
        $this->assertEquals(25.00, (float) $waste->unit_cost);
        $this->assertEquals(100.00, (float) $waste->total_cost);
        $this->assertEquals($this->productionOrder->id, $waste->production_order_id);
    }
}
