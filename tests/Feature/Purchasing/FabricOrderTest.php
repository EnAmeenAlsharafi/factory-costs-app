<?php

namespace Tests\Feature\Purchasing;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\FabricMaterialSpec;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProductionOrder;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FabricOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private int $sequence = 800;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->buyer = User::factory()->create([
            'role_id' => Role::where('name', 'purchasing_user')->first()->id,
            'is_active' => true,
        ]);
    }

    public function test_meters_of_the_same_catalog_and_colour_are_summed_into_one_order_per_supplier(): void
    {
        $velvet = $this->fabric('الخليج للأقمشة', '2023');
        $colour5 = $this->colour($velvet, '5');
        $colour7 = $this->colour($velvet, '7');
        $linen = $this->fabric('النسيج الحديث', 'L-88');
        $linenColour = $this->colour($linen, '12');

        $firstBed = $this->productionOrder($velvet, $colour5);
        $secondBed = $this->productionOrder($velvet, $colour5);
        $thirdBed = $this->productionOrder($velvet, $colour7);
        $otherSupplierBed = $this->productionOrder($linen, $linenColour);

        $this->actingAs($this->buyer)->get(route('purchasing.fabric-orders.index'))
            ->assertOk()
            ->assertSee($firstBed->production_order_number);

        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'extra_percent' => 0,
            'items' => [
                $firstBed->id => ['selected' => 1, 'meters' => 10],
                $secondBed->id => ['selected' => 1, 'meters' => 12],
                $thirdBed->id => ['selected' => 1, 'meters' => 9.3],
                $otherSupplierBed->id => ['selected' => 1, 'meters' => 8],
            ],
        ])->assertRedirect(route('purchasing.fabric-orders.index'))->assertSessionHasNoErrors();

        $velvetOrder = PurchaseOrder::where('supplier_id', $velvet->fabricSpec->supplier_id)->sole();
        $this->assertSame('DRAFT', $velvetOrder->status);
        $this->assertSame('RAW_MATERIALS', $velvetOrder->warehouse->code);
        $this->assertCount(2, $velvetOrder->lines);
        $colour5Line = $velvetOrder->lines->firstWhere('fabric_color_id', $colour5->id);
        $this->assertEquals(22.0, (float) $colour5Line->ordered_quantity);
        $this->assertEqualsCanonicalizing([$firstBed->id, $secondBed->id], $colour5Line->fabricAllocations->pluck('production_order_id')->all());
        // 9.3 m is rounded up to the next half metre.
        $this->assertEquals(9.5, (float) $velvetOrder->lines->firstWhere('fabric_color_id', $colour7->id)->ordered_quantity);

        $this->assertSame(1, PurchaseOrder::where('supplier_id', $linen->fabricSpec->supplier_id)->count());

        // The beds no longer wait for fabric, so ordering them again is refused.
        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'items' => [$firstBed->id => ['selected' => 1, 'meters' => 10]],
        ])->assertSessionHasErrors('items');
        $this->assertSame(2, PurchaseOrder::count());
    }

    public function test_safety_margin_is_added_before_rounding(): void
    {
        $velvet = $this->fabric('الخليج للأقمشة', '2023');
        $bed = $this->productionOrder($velvet, $this->colour($velvet, '5'));

        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'extra_percent' => 10,
            'items' => [$bed->id => ['selected' => 1, 'meters' => 10]],
        ]);

        $line = PurchaseOrder::sole()->lines->sole();
        $this->assertEquals(11.0, (float) $line->ordered_quantity);
        $this->assertEquals(10.0, (float) $line->fabricAllocations->sole()->meters);
    }

    public function test_print_sheet_shows_the_company_and_the_summed_catalog_lines(): void
    {
        $velvet = $this->fabric('الخليج للأقمشة', '2023');
        $colour = $this->colour($velvet, '5');
        $beds = [$this->productionOrder($velvet, $colour), $this->productionOrder($velvet, $colour)];

        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'items' => [$beds[0]->id => ['selected' => 1, 'meters' => 10], $beds[1]->id => ['selected' => 1, 'meters' => 12]],
        ]);

        $this->actingAs($this->buyer)->get(route('purchasing.fabric-orders.print', PurchaseOrder::sole()))
            ->assertOk()
            ->assertSee('طلب توريد قماش')
            ->assertSee('المصنع')
            ->assertDontSee('مستودع المنتجات الجاهزة')
            ->assertSee('مصنع مفروشات سدير')
            ->assertSee('الخليج للأقمشة')
            ->assertSee('2023')
            ->assertSee('22')
            ->assertSee($beds[0]->production_order_number);
    }

    public function test_fabric_order_downloads_as_a_pdf(): void
    {
        $velvet = $this->fabric('الخليج للأقمشة', '2023');
        $bed = $this->productionOrder($velvet, $this->colour($velvet, '5'));

        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'items' => [$bed->id => ['selected' => 1, 'meters' => 10]],
        ]);
        $purchaseOrder = PurchaseOrder::sole();

        $response = $this->actingAs($this->buyer)->get(route('purchasing.fabric-orders.pdf', $purchaseOrder));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('fabric-order-'.$purchaseOrder->purchase_order_number.'.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bed_without_linked_fabric_cannot_be_ordered(): void
    {
        $velvet = $this->fabric('الخليج للأقمشة', '2023');
        $bed = $this->productionOrder($velvet, null);

        $this->actingAs($this->buyer)->post(route('purchasing.fabric-orders.store'), [
            'warehouse_id' => Warehouse::active()->firstOrFail()->id,
            'items' => [$bed->id => ['selected' => 1, 'meters' => 10]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, PurchaseOrder::count());
    }

    private function fabric(string $supplierName, string $catalog): Material
    {
        $this->sequence++;
        $supplier = Supplier::create(['name' => $supplierName, 'supplier_code' => 'SUP-F'.$this->sequence, 'is_active' => true]);
        $material = Material::create([
            'code' => 'FAB-'.$this->sequence,
            'name_ar' => 'قماش مخمل '.$catalog,
            'material_category_id' => MaterialCategory::where('code', 'FABRIC')->firstOrFail()->id,
            'base_unit_id' => (UnitOfMeasure::where('code', 'METER')->first() ?? UnitOfMeasure::firstOrFail())->id,
            'is_active' => true,
        ]);
        FabricMaterialSpec::create(['material_id' => $material->id, 'supplier_id' => $supplier->id, 'catalog_number' => $catalog, 'fabric_type' => 'مخمل', 'width_cm' => 140]);

        return $material->load('fabricSpec');
    }

    private function colour(Material $fabric, string $supplierCode): FabricColor
    {
        return FabricColor::create([
            'material_id' => $fabric->id,
            'color_code' => 'C-'.$supplierCode.'-'.$fabric->id,
            'supplier_color_code' => $supplierCode,
            'color_name_ar' => 'لون '.$supplierCode,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    private function productionOrder(Material $fabric, ?FabricColor $colour): ProductionOrder
    {
        $this->sequence++;
        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000'.$this->sequence,
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000,
            'created_by_user_id' => $this->buyer->id,
        ]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'item_number' => 1,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
        ]);

        return ProductionOrder::create([
            'production_order_number' => 'PRO-2026-000'.$this->sequence,
            'customer_order_id' => $order->id,
            'customer_order_line_id' => $line->id,
            'ordered_quantity' => 1,
            'released_quantity' => 1,
            'status' => 'RELEASED',
            'fabric_material_id' => $fabric->id,
            'fabric_supplier_id' => $fabric->fabricSpec->supplier_id,
            'fabric_color_id' => $colour?->id,
            'fabric_supplier_color_code' => $colour?->supplier_color_code,
        ]);
    }
}
