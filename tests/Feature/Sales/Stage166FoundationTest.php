<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 16.6 — foundation layer: schema/model changes only, no UI/import
 * logic yet. Verifies existing behavior is unaffected and the new
 * nullable/line_type plumbing works.
 */
class Stage166FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected CustomerOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $role = Role::where('name', 'production_manager')->first();
        $this->manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);

        $this->order = CustomerOrder::create([
            'order_number' => 'ORD-2026-000800',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'DRAFT',
            'order_date' => now(),
            'total_amount' => 0,
            'created_by_user_id' => $this->manager->id,
        ]);
    }

    public function test_historical_style_manufactured_line_still_works_without_line_type(): void
    {
        $line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        $this->assertEquals('MANUFACTURED', $line->fresh()->line_type);
        $this->assertEquals('NO_RECIPE', $line->recipe_status);
    }

    public function test_ready_stock_line_can_be_created_without_dimensions(): void
    {
        $unit = UnitOfMeasure::first();
        $category = MaterialCategory::where('code', 'READY_GOODS')->first();
        $this->assertNotNull($category, 'READY_GOODS material category was not seeded.');

        $material = Material::create([
            'code' => 'MAT-READY-TEST-0001',
            'name_ar' => 'مرتبة اختبار',
            'material_category_id' => $category->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
            'sales_sku' => 'SKU-TEST-0001',
            'sales_display_name' => 'مرتبة طبية اختبار',
        ]);

        $line = CustomerOrderLine::create([
            'customer_order_id' => $this->order->id,
            'line_type' => 'READY_STOCK',
            'material_id' => $material->id,
            'quantity' => 2,
            'unit_price' => 500,
            'line_total' => 1000,
        ]);

        $this->assertNull($line->requested_width_cm);
        $this->assertNull($line->requested_length_cm);
        $this->assertEquals('مرتبة طبية اختبار', $line->display_name);
        $this->assertEquals('NOT_APPLICABLE', $line->recipe_status);
    }

    public function test_sellable_ready_product_is_excluded_from_recipe_material_selector(): void
    {
        $unit = UnitOfMeasure::first();
        $readyCategory = MaterialCategory::where('code', 'READY_GOODS')->first();

        $readyMaterial = Material::create([
            'code' => 'MAT-READY-TEST-0002',
            'name_ar' => 'منتج جاهز للاستبعاد',
            'material_category_id' => $readyCategory->id,
            'base_unit_id' => $unit->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        $response = $this->actingAs($this->manager)->get(route('recipes.create'));

        $response->assertOk();
        $materials = $response->viewData('materials');
        $this->assertFalse($materials->contains('id', $readyMaterial->id));
    }

    public function test_manual_order_request_still_requires_dimensions_for_manufactured_line(): void
    {
        $csRole = Role::where('name', 'customer_service')->first();
        $csUser = User::factory()->create(['role_id' => $csRole->id, 'is_active' => true]);

        $response = $this->actingAs($csUser)->post(route('sales.orders.store'), [
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'order_date' => now()->format('Y-m-d'),
            'priority' => 'NORMAL',
            'lines' => [
                ['quantity' => 1, 'unit_price' => 1000],
            ],
        ]);

        $response->assertSessionHasErrors(['lines.0.requested_width_cm', 'lines.0.requested_length_cm']);
    }
}
