<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\InventoryLot;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueLine;
use App\Models\MaterialReturn;
use App\Models\ProductionOrder;
use App\Models\ProductModel;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fixtures for Stage 14 reporting tests: orders, production orders and POSTED material documents written directly,
 * so each test controls exact quantities and lot costs.
 */
abstract class ReportingTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Warehouse $warehouse;

    protected Material $material;

    protected ProductModel $milan;

    protected int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('username', 'admin')->firstOrFail();
        $this->warehouse = Warehouse::orderBy('id')->firstOrFail();
        $this->material = Material::create([
            'code' => 'MAT-RPT-001',
            'name_ar' => 'خشب اختبار التقارير',
            'material_category_id' => MaterialCategory::firstOrCreate(['code' => 'WOOD'], ['name_ar' => 'خشب', 'is_active' => true])->id,
            'base_unit_id' => UnitOfMeasure::orderBy('id')->value('id'),
            'is_active' => true,
        ]);
        $this->milan = ProductModel::create(['model_code' => 'MILAN-RPT', 'name_ar' => 'سرير ميلان', 'category' => 'BED', 'is_active' => true]);
    }

    protected function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id, 'is_active' => true]);
    }

    /**
     * @return array{0: CustomerOrder, 1: CustomerOrderLine}
     */
    protected function order(float $value, float $quantity = 1, string $status = 'APPROVED_FOR_PRODUCTION', ?string $date = null, ?ProductModel $model = null): array
    {
        $this->sequence++;
        $order = CustomerOrder::create([
            'order_number' => 'ORD-RPT-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'customer_id' => Customer::orderBy('id')->value('id'),
            'sales_channel_id' => SalesChannel::orderBy('id')->value('id'),
            'created_by_user_id' => $this->admin->id,
            'status' => $status,
            'order_date' => $date ?? now()->toDateString(),
            'total_amount' => $value,
            'subtotal' => $value,
        ]);
        $line = CustomerOrderLine::create([
            'customer_order_id' => $order->id,
            'product_model_id' => ($model ?? $this->milan)->id,
            'requested_width_cm' => 160,
            'requested_length_cm' => 200,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? $value / $quantity : $value,
            'line_total' => $value,
        ]);

        return [$order, $line];
    }

    protected function productionOrder(CustomerOrderLine $line, int $released, string $status = 'COMPLETED'): ProductionOrder
    {
        $this->sequence++;

        return ProductionOrder::create([
            'production_order_number' => 'PRO-RPT-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'customer_order_id' => $line->customer_order_id,
            'customer_order_line_id' => $line->id,
            'product_model_id' => $line->product_model_id,
            'ordered_quantity' => (int) $line->quantity,
            'released_quantity' => $released,
            'completed_quantity' => $status === 'COMPLETED' ? $released : 0,
            'status' => $status,
            'released_at' => now()->subDay(),
            'completed_at' => $status === 'COMPLETED' ? now() : null,
        ]);
    }

    protected function lot(float $remaining, float $unitCost, ?Material $material = null, ?float $original = null, string $code = ''): InventoryLot
    {
        $this->sequence++;
        $material ??= $this->material;

        return InventoryLot::create([
            'lot_code' => $code ?: 'LOT-RPT-'.$this->sequence,
            'material_id' => $material->id,
            'warehouse_id' => $this->warehouse->id,
            'received_date' => now()->subDays(5),
            'original_quantity' => $original ?? $remaining,
            'remaining_quantity' => $remaining,
            'base_unit_id' => $material->base_unit_id,
            'unit_cost' => $unitCost,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * A POSTED issue to the production order. Each line: [lot, quantity, reason].
     *
     * @param  list<array{0: InventoryLot, 1: float, 2?: string}>  $lines
     */
    protected function postedIssue(ProductionOrder $po, array $lines): MaterialIssue
    {
        $this->sequence++;
        $issue = MaterialIssue::create([
            'issue_number' => 'ISS-RPT-'.$this->sequence,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now()->toDateString(),
            'status' => 'POSTED',
            'created_by_user_id' => $this->admin->id,
            'posted_at' => now(),
            'production_order_id' => $po->id,
        ]);
        foreach ($lines as $line) {
            [$lot, $quantity] = $line;
            MaterialIssueLine::create([
                'material_issue_id' => $issue->id,
                'material_id' => $lot->material_id,
                'inventory_lot_id' => $lot->id,
                'issued_quantity' => $quantity,
                'base_unit_id' => $lot->base_unit_id,
                'unit_cost' => $lot->unit_cost,
                'total_cost' => round($quantity * (float) $lot->unit_cost, 4),
                'request_reason' => $line[2] ?? 'PLANNED_PRODUCTION',
            ]);
        }

        return $issue;
    }

    protected function postedReturn(ProductionOrder $po, InventoryLot $lot, float $quantity): MaterialReturn
    {
        $this->sequence++;
        $return = MaterialReturn::create([
            'return_number' => 'RET-RPT-'.$this->sequence,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'status' => 'POSTED',
            'created_by_user_id' => $this->admin->id,
            'posted_at' => now(),
            'production_order_id' => $po->id,
        ]);
        $return->lines()->create([
            'material_id' => $lot->material_id,
            'inventory_lot_id' => $lot->id,
            'returned_quantity' => $quantity,
            'base_unit_id' => $lot->base_unit_id,
            'unit_cost' => $lot->unit_cost,
            'total_cost' => round($quantity * (float) $lot->unit_cost, 4),
        ]);

        return $return;
    }
}
