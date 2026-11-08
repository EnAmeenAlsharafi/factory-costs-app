<?php

namespace Tests\Feature\Purchasing;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\ReadyProductSupplySetting;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\SupplierProductionRequest;
use App\Models\SupplierStockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReadyProductSupplyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MattressSupplyTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Supplier $supplier;

    private int $sequence = 900;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->buyer = User::factory()->create(['role_id' => Role::where('name', 'purchasing_user')->first()->id, 'is_active' => true]);
        $this->supplier = Supplier::create(['name' => 'مصنع الراحة للمراتب', 'supplier_code' => 'SUP-MAT-1', 'is_active' => true]);
    }

    public function test_stock_counts_ours_and_the_suppliers_and_alerts_at_the_reorder_level(): void
    {
        $size = $this->mattressSize('100*200', reorderLevel: 5, batch: 10);
        $this->ourStock($size, 2);
        $this->orderLine($size, 4);

        $this->actingAs($this->buyer)->put(route('purchasing.mattresses.settings.update', $size->supplySetting), [
            'supplier_id' => $this->supplier->id,
            'reorder_level' => 5,
            'production_batch_size' => 10,
            'supplier_balance' => 6,
        ])->assertSessionHasNoErrors();

        $row = app(ReadyProductSupplyService::class)->overview()->sole();
        $this->assertEquals(2, $row['on_hand']);
        $this->assertEquals(6, $row['at_supplier']);
        $this->assertEquals(4, $row['reserved']);
        $this->assertEquals(4, $row['available']);
        $this->assertTrue($row['is_low']);
        $this->assertSame(10, $row['suggested']);

        $this->actingAs($this->buyer)->get(route('purchasing.mattresses.stock'))
            ->assertOk()
            ->assertSee('مقاس وصل للحد الأدنى')
            ->assertSee('100*200');
        $this->actingAs($this->buyer)->get(route('dashboard'))->assertSee('مقاس مراتب');
    }

    public function test_supply_order_sums_sizes_across_orders_and_bundles_per_supplier(): void
    {
        $size = $this->mattressSize('100*200');
        $other = $this->mattressSize('120*200');
        $first = $this->orderLine($size, 2);
        $second = $this->orderLine($size, 3);
        $bundleChild = $this->orderLine($other, 1, inBundle: true);

        $this->actingAs($this->buyer)->get(route('purchasing.mattresses.orders'))->assertOk()->assertSee($first->customerOrder->order_number);

        $this->actingAs($this->buyer)->post(route('purchasing.mattresses.orders.store'), [
            'items' => [
                $first->id => ['selected' => 1, 'quantity' => 2],
                $second->id => ['selected' => 1, 'quantity' => 3],
                $bundleChild->id => ['selected' => 1, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $order = PurchaseOrder::sole();
        $this->assertSame($this->supplier->id, $order->supplier_id);
        $this->assertSame('READY_GOODS', $order->warehouse->code);
        $this->assertEquals(5.0, (float) $order->lines->firstWhere('material_id', $size->id)->ordered_quantity);
        $this->assertEquals(1.0, (float) $order->lines->firstWhere('material_id', $other->id)->ordered_quantity);

        // Already-ordered lines leave the list and cannot be ordered twice.
        $this->assertTrue(app(ReadyProductSupplyService::class)->demand()->isEmpty());
        $this->actingAs($this->buyer)->post(route('purchasing.mattresses.orders.store'), [
            'items' => [$first->id => ['selected' => 1, 'quantity' => 2]],
        ])->assertSessionHasErrors('items');

        $response = $this->actingAs($this->buyer)->get(route('purchasing.mattresses.orders.pdf', $order));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_production_request_respects_the_batch_and_adds_to_the_suppliers_stock_when_produced(): void
    {
        $size = $this->mattressSize('100*200', reorderLevel: 5, batch: 10);

        $this->actingAs($this->buyer)->post(route('purchasing.mattresses.production.store'), [
            'supplier_id' => $this->supplier->id,
            'items' => [$size->id => ['selected' => 1, 'quantity' => 6]],
        ])->assertSessionHasErrors('items');

        $this->actingAs($this->buyer)->post(route('purchasing.mattresses.production.store'), [
            'supplier_id' => $this->supplier->id,
            'items' => [$size->id => ['selected' => 1, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();

        $request = SupplierProductionRequest::sole();
        $this->assertSame('REQUESTED', $request->status);

        $pdf = $this->actingAs($this->buyer)->get(route('purchasing.mattresses.production.pdf', $request));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->buyer)->post(route('purchasing.mattresses.production.confirm', $request), [
            'produced' => [$request->lines->sole()->id => 12],
        ])->assertSessionHasNoErrors();

        $this->assertSame('PRODUCED', $request->fresh()->status);
        $this->assertEquals(12, app(ReadyProductSupplyService::class)->overview()->sole()['at_supplier']);
    }

    public function test_receiving_from_the_supplier_takes_the_quantity_out_of_their_stock(): void
    {
        $size = $this->mattressSize('100*200');
        app(ReadyProductSupplyService::class)->setSupplierBalance($size->supplySetting, 8, $this->buyer);
        $order = $this->orderLine($size, 3);
        $purchaseOrder = app(ReadyProductSupplyService::class)->createSupplyOrders([$order->id => 3], $this->buyer)->sole();

        $receipt = new MaterialReceipt(['receipt_number' => 'REC-TEST-1']);
        $receipt->setRelation('lines', collect([new MaterialReceiptLine(['material_id' => $size->id, 'base_quantity' => 3])]));

        app(ReadyProductSupplyService::class)->recordDeliveryFromReceipt($receipt, $purchaseOrder, $this->buyer);

        $this->assertEquals(5, (float) SupplierStockMovement::where('material_id', $size->id)->sum('quantity'));
        $this->assertSame('DELIVERED', SupplierStockMovement::latest('id')->first()->movement_type);
    }

    public function test_only_purchasing_staff_manage_mattress_supply(): void
    {
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);

        $this->actingAs($worker)->get(route('purchasing.mattresses.stock'))->assertForbidden();
        $this->actingAs($worker)->get(route('purchasing.mattresses.orders'))->assertForbidden();
    }

    private function mattressSize(string $size, int $reorderLevel = 0, int $batch = 1): Material
    {
        $this->sequence++;
        $category = MaterialCategory::where('code', 'READY_GOODS')->firstOrFail();
        $unit = UnitOfMeasure::firstOrFail();
        $parent = Material::create([
            'code' => 'MAT-M'.$this->sequence,
            'name_ar' => 'مرتبة فندقية '.$this->sequence.' | مقاسات متعددة',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
            'has_ready_options' => true,
        ]);
        $option = Material::create([
            'code' => 'MAT-M'.$this->sequence.'-'.$size,
            'name_ar' => $parent->name_ar.' — '.$size,
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
            'ready_parent_id' => $parent->id,
            'ready_option_label' => $size,
        ]);
        ReadyProductSupplySetting::create([
            'material_id' => $option->id,
            'supplier_id' => $this->supplier->id,
            'reorder_level' => $reorderLevel,
            'production_batch_size' => $batch,
        ]);

        return $option->load('supplySetting');
    }

    private function ourStock(Material $size, float $quantity): void
    {
        InventoryLot::create([
            'lot_code' => 'LOT-M'.(++$this->sequence),
            'material_id' => $size->id,
            'warehouse_id' => Warehouse::where('code', 'READY_GOODS')->firstOrFail()->id,
            'received_date' => now(),
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'base_unit_id' => $size->base_unit_id,
            'unit_cost' => 500,
            'status' => 'ACTIVE',
        ]);
    }

    private function orderLine(Material $size, int $quantity, bool $inBundle = false): CustomerOrderLine
    {
        $this->sequence++;
        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-00'.$this->sequence,
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000,
            'created_by_user_id' => $this->buyer->id,
        ]);
        $parent = $inBundle ? CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'line_type' => 'BUNDLE',
            'item_number' => 1,
            'external_product_name' => 'بكج غرفة نوم',
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]) : null;

        return CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'line_type' => 'READY_STOCK',
            'parent_line_id' => $parent?->id,
            'item_number' => $inBundle ? 2 : 1,
            'material_id' => $size->id,
            'quantity' => $quantity,
            'unit_price' => 500,
            'total_price' => 500 * $quantity,
        ])->load('customerOrder');
    }
}
