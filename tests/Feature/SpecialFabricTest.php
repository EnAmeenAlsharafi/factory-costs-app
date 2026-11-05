<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\FabricColor;
use App\Models\InventoryLot;
use App\Models\ManufacturingRecipeItem;
use App\Models\ManufacturingRecipeVersion;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\MaterialReceipt;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkCenter;
use App\Services\InventoryService;
use App\Services\ProductionExecutionService;
use App\Services\ProductionMaterialRequestService;
use App\Services\ProductionOrderService;
use App\Services\SpecialFabricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SpecialFabricTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_link_registered_fabric_after_confirmation_without_changing_description_or_prices(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();

        $this->actingAs($admin)->get(route('sales.orders.show', $line->customerOrder))->assertSee('حفظ الربط المخزني')->assertSee('كتالوج العميل');
        $this->put(route('sales.orders.fabric.update', [$line->customerOrder, $line]), ['fabric_color_id' => $color->id, 'unit_price' => 1, 'fabric_notes' => 'forged'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($color->material_id, $line->fresh()->fabric_material_id);
        $this->assertSame($color->id, $po->fresh()->fabric_color_id);
        $this->assertSame('550.00', $line->fresh()->unit_price);
        $this->assertSame('قماش العميل: كتالوج العميل — لون العميل: بيج', $line->fresh()->fabric_notes);
        $this->assertDatabaseHas('customer_order_changes', ['customer_order_line_id' => $line->id, 'field_name' => 'special_fabric_link']);
    }

    public function test_link_requires_staff_permission_and_the_line_must_belong_to_the_order(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();
        $url = route('sales.orders.fabric.update', [$line->customerOrder, $line]);
        $this->put($url, ['fabric_color_id' => $color->id])->assertRedirect(route('login'));
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->value('id'), 'is_active' => true]);
        $this->actingAs($worker)->put($url, ['fabric_color_id' => $color->id])->assertForbidden();
        $otherOrder = CustomerOrder::factory()->create();
        $this->actingAs($admin)->put(route('sales.orders.fabric.update', [$otherOrder, $line]), ['fabric_color_id' => $color->id])->assertNotFound();
        $this->assertNull($line->fresh()->fabric_material_id);
    }

    public function test_link_rejects_fabric_without_supplier_and_catalog(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();
        $color->material->fabricSpec()->delete();

        $this->actingAs($admin)->put(route('sales.orders.fabric.update', [$line->customerOrder, $line]), ['fabric_color_id' => $color->id])->assertSessionHasErrors('fabric');

        $this->assertNull($line->fresh()->fabric_material_id);
        $this->assertNull($po->fresh()->fabric_material_id);
    }

    public function test_unresolved_special_fabric_never_falls_back_to_recipe_fabric_and_can_be_added_after_wood_request(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $version = ManufacturingRecipeVersion::factory()->create();
        $fabric = $this->fabric();
        $wood = Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'WOOD')->value('id')]);
        foreach ([$wood, $fabric->material] as $material) {
            ManufacturingRecipeItem::factory()->create(['recipe_version_id' => $version->id, 'material_id' => $material->id, 'unit_id' => $material->base_unit_id]);
        }
        $po->update(['manufacturing_recipe_version_id' => $version->id]);
        $service = app(ProductionMaterialRequestService::class);

        $service->createMaterialRequirementsFromRecipe($po->fresh());

        $this->assertSame([$wood->id], $po->materialRequirements()->pluck('material_id')->all());
        $service->createRequest($po, $admin, ['warehouse_id' => Warehouse::first()->id, 'lines' => [['material_id' => $wood->id, 'requested_quantity' => 1, 'base_unit_id' => $wood->base_unit_id]]]);
        app(SpecialFabricService::class)->link($line, $fabric->id, $admin);
        $service->createMaterialRequirementsFromRecipe($po->fresh());
        $this->assertCount(2, $po->materialRequirements()->get());
        $this->assertDatabaseHas('production_material_requirements', ['production_order_id' => $po->id, 'material_id' => $fabric->material_id]);
    }

    public function test_fabric_issue_is_blocked_before_link_and_before_actual_receipt(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();
        $lot = InventoryLot::create(['lot_code' => 'UNRECEIVED', 'material_id' => $color->material_id, 'fabric_color_id' => $color->id,
            'supplier_id' => $color->material->fabricSpec->supplier_id, 'warehouse_id' => Warehouse::first()->id, 'received_date' => today(),
            'original_quantity' => 10, 'remaining_quantity' => 10, 'base_unit_id' => $color->material->base_unit_id, 'unit_cost' => 5, 'status' => 'ACTIVE']);
        $issue = $this->issue($po, $lot, $admin);
        $this->assertIssueRejected($issue, $admin);
        app(SpecialFabricService::class)->link($line, $color->id, $admin);
        $this->assertIssueRejected($issue, $admin);
        $this->assertSame('10.0000', $lot->fresh()->remaining_quantity);
        $this->assertSame('DRAFT', $issue->fresh()->status);
    }

    public function test_receipt_and_issue_of_correct_fabric_unlock_upholstery_and_link_cannot_change_after_issue(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();
        app(SpecialFabricService::class)->link($line, $color->id, $admin);
        $operation = $this->operation($po, 'OP_UPHOLSTERY');
        try {
            app(ProductionExecutionService::class)->start($operation, $admin);
            $this->fail('Upholstery started without issued fabric.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fabric', $exception->errors());
        }
        $lot = $this->receive($color, $admin);
        $wrongColor = FabricColor::factory()->create(['material_id' => $color->material_id]);
        $lot->update(['fabric_color_id' => $wrongColor->id]);
        $issue = $this->issue($po, $lot, $admin);
        $this->assertIssueRejected($issue, $admin);
        $lot->update(['fabric_color_id' => $color->id]);
        $issue->lines()->update(['fabric_color_id' => $color->id]);

        app(InventoryService::class)->postIssue($issue->fresh(), $admin);
        $started = app(ProductionExecutionService::class)->start($operation->fresh(), $admin);

        $this->assertSame('IN_PROGRESS', $started->status);
        $this->assertSame('8.0000', $lot->fresh()->remaining_quantity);
        $this->actingAs($admin)->put(route('sales.orders.fabric.update', [$line->customerOrder, $line]), ['fabric_color_id' => $wrongColor->id])->assertSessionHasErrors('fabric');
        $this->assertSame($color->id, $po->fresh()->fabric_color_id);
    }

    public function test_carpentry_can_start_while_special_fabric_is_pending_but_upholstery_progress_cannot_bypass_it(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $carpentry = $this->operation($po, 'OP_CARPENTRY');
        $this->assertSame('IN_PROGRESS', app(ProductionExecutionService::class)->start($carpentry, $admin)->status);
        $upholstery = $this->operation($po, 'OP_UPHOLSTERY');

        $this->expectException(ValidationException::class);
        app(ProductionOrderService::class)->recordProgress($upholstery, 1, 'PROGRESS', null, $admin);
    }

    public function test_material_request_cannot_use_unresolved_or_different_fabric_and_freezes_the_link_after_creation(): void
    {
        $this->seed();
        [$line, $po, $admin] = $this->production();
        $color = $this->fabric();
        $service = app(ProductionMaterialRequestService::class);
        $data = ['warehouse_id' => Warehouse::first()->id, 'lines' => [['material_id' => $color->material_id,
            'requested_quantity' => 2, 'base_unit_id' => $color->material->base_unit_id]]];
        try {
            $service->createRequest($po, $admin, $data);
            $this->fail('Fabric was requested without linking.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fabric', $exception->errors());
        }
        $this->assertSame(0, $po->materialRequests()->count());
        app(SpecialFabricService::class)->link($line, $color->id, $admin);
        $other = $this->fabric();
        $wrong = $data;
        $wrong['lines'][0]['material_id'] = $other->material_id;
        try {
            $service->createRequest($po->fresh(), $admin, $wrong);
            $this->fail('Different fabric was requested.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fabric', $exception->errors());
        }
        $service->createRequest($po->fresh(), $admin, $data);
        $this->actingAs($admin)->put(route('sales.orders.fabric.update', [$line->customerOrder, $line]), ['fabric_color_id' => $other->id])->assertSessionHasErrors('fabric');
        $this->assertSame($color->id, $line->fresh()->fabric_color_id);
        $this->assertSame(1, $po->materialRequests()->count());
    }

    /** @return array{CustomerOrderLine, ProductionOrder, User} */
    private function production(): array
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_active' => true]);
        $order = CustomerOrder::factory()->create(['status' => 'APPROVED_FOR_PRODUCTION']);
        $line = CustomerOrderLine::factory()->create(['customer_order_id' => $order->id, 'special_fabric' => true, 'unit_price' => 550,
            'fabric_notes' => 'قماش العميل: كتالوج العميل — لون العميل: بيج']);
        $po = app(ProductionOrderService::class)->createFromOrderLine($line, ['released_quantity' => 1]);
        $po->update(['status' => 'RELEASED']);

        return [$line, $po, $admin];
    }

    private function fabric(): FabricColor
    {
        $material = Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'FABRIC')->value('id'), 'is_active' => true]);
        $material->fabricSpec()->create(['supplier_id' => Supplier::factory()->create(['is_active' => true])->id, 'catalog_number' => 'CUSTOM-2026', 'fabric_type' => 'VELVET', 'width_cm' => 140]);

        return FabricColor::factory()->create(['material_id' => $material->id, 'is_available' => true]);
    }

    private function operation(ProductionOrder $po, string $code): ProductionOrderOperation
    {
        return $po->operations()->create(['work_center_id' => WorkCenter::where('code', $code === 'OP_CARPENTRY' ? 'WC_CARPENTRY' : 'WC_UPHOLSTERY')->value('id'),
            'operation_code' => $code, 'operation_name_snapshot' => $code, 'sequence_number' => 1, 'required_quantity' => 1, 'status' => 'READY']);
    }

    private function receive(FabricColor $color, User $admin): InventoryLot
    {
        $receipt = MaterialReceipt::create(['receipt_number' => 'SPECIAL-RECEIPT', 'supplier_id' => $color->material->fabricSpec->supplier_id,
            'warehouse_id' => Warehouse::first()->id, 'receipt_date' => today(), 'status' => 'DRAFT', 'created_by_user_id' => $admin->id]);
        $receiptLine = $receipt->lines()->create(['material_id' => $color->material_id, 'fabric_color_id' => $color->id, 'quantity_received' => 10,
            'purchase_unit_id' => $color->material->base_unit_id, 'base_unit_id' => $color->material->base_unit_id, 'conversion_factor' => 1,
            'base_quantity' => 10, 'unit_cost_purchase' => 5, 'unit_cost_base' => 5, 'total_cost' => 50]);
        app(InventoryService::class)->postReceipt($receipt, $admin);

        return $receiptLine->lot()->firstOrFail();
    }

    private function issue(ProductionOrder $po, InventoryLot $lot, User $admin): MaterialIssue
    {
        $issue = MaterialIssue::create(['issue_number' => 'SPECIAL-ISSUE', 'warehouse_id' => $lot->warehouse_id, 'issue_date' => today(),
            'production_order_id' => $po->id, 'status' => 'DRAFT', 'created_by_user_id' => $admin->id]);
        $issue->lines()->create(['material_id' => $lot->material_id, 'fabric_color_id' => $lot->fabric_color_id, 'inventory_lot_id' => $lot->id,
            'requested_quantity' => 2, 'issued_quantity' => 2, 'base_unit_id' => $lot->base_unit_id, 'unit_cost' => 5, 'total_cost' => 10]);

        return $issue;
    }

    private function assertIssueRejected(MaterialIssue $issue, User $admin): void
    {
        try {
            app(InventoryService::class)->postIssue($issue->fresh(), $admin);
            $this->fail('Unapproved fabric was issued.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fabric', $exception->errors());
        }
    }
}
