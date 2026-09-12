<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Tests\Feature\Production\ProductionMaterialRequestTest;

class InventoryCostVisibilityTest extends ProductionMaterialRequestTest
{
    public function test_warehouse_keeper_can_use_inventory_without_seeing_lot_costs(): void
    {
        $this->lot->update(['unit_cost' => 9876.543210]);

        $response = $this->actingAs($this->warehouseKeeper)
            ->get(route('inventory.balances.index'));

        $response->assertOk();
        $response->assertSee('سري');
        $response->assertDontSee('9,876.54');
        $response->assertDontSee('9876.543210');
    }

    public function test_administrator_can_see_lot_costs(): void
    {
        $this->lot->update(['unit_cost' => 9876.543210]);
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.balances.index'))
            ->assertOk()
            ->assertSee('9,876.54');
    }
}
