<?php

namespace Tests\Feature;

use App\Models\ProductionWasteReason;
use App\Models\ProductionWasteRecord;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductionMaterialRequestService;
use Tests\Feature\Production\ProductionMaterialRequestTest;

class ProductionAuthorizationMatrixTest extends ProductionMaterialRequestTest
{
    public function test_mutation_endpoints_enforce_the_canonical_role_matrix(): void
    {
        $users = collect([
            'admin',
            'production_manager',
            'production_worker',
            'warehouse_keeper',
            'customer_service',
            'sales_user',
        ])->mapWithKeys(fn ($roleName) => [$roleName => User::factory()->create([
            'role_id' => Role::where('name', $roleName)->firstOrFail()->id,
            'is_active' => true,
            'department_id' => $roleName === 'production_worker' ? $this->manager->department_id : null,
        ])]);

        $matrix = [
            'production.orders.store' => ['admin', 'production_manager'],
            'production.routings.store' => ['admin', 'production_manager'],
            'production.material-requests.store' => ['admin', 'production_manager'],
            'production.quality-incidents.store' => ['admin', 'production_manager', 'production_worker'],
            'production.waste.store' => ['admin', 'production_manager', 'production_worker'],
            'production.rework.store' => ['admin', 'production_manager'],
        ];

        foreach ($matrix as $routeName => $allowedRoles) {
            foreach ($users as $roleName => $user) {
                $response = $this->actingAs($user)->post(route($routeName), []);

                if (in_array($roleName, $allowedRoles, true)) {
                    $this->assertNotSame(403, $response->getStatusCode(), "{$roleName} was denied {$routeName}");
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_only_inventory_issuer_can_fulfill_and_only_manager_can_approve_waste(): void
    {
        $service = app(ProductionMaterialRequestService::class);
        $request = $service->createRequest($this->productionOrder, $this->manager, [
            'warehouse_id' => $this->warehouse->id,
            'lines' => [[
                'material_id' => $this->material->id,
                'requested_quantity' => 1,
                'base_unit_id' => $this->material->base_unit_id,
            ]],
        ]);
        $service->submitRequest($request);

        $customerService = User::factory()->create([
            'role_id' => Role::where('name', 'customer_service')->firstOrFail()->id,
            'is_active' => true,
        ]);

        $this->actingAs($customerService)
            ->post(route('production.material-requests.fulfill', $request), [])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->post(route('production.material-requests.fulfill', $request), [])
            ->assertForbidden();

        $this->actingAs($this->warehouseKeeper)
            ->post(route('production.material-requests.fulfill', $request), [])
            ->assertSessionHasErrors('fulfillments');

        $waste = ProductionWasteRecord::create([
            'waste_number' => 'WST-AUTH-001',
            'production_order_id' => $this->productionOrder->id,
            'material_id' => $this->material->id,
            'quantity' => 1,
            'unit_id' => $this->material->base_unit_id,
            'unit_cost' => 1,
            'total_cost' => 1,
            'waste_reason_id' => ProductionWasteReason::firstOrFail()->id,
            'recorded_by_user_id' => $this->manager->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->warehouseKeeper)
            ->post(route('production.waste.approve', $waste))
            ->assertForbidden();
        $this->actingAs($this->manager)
            ->post(route('production.waste.approve', $waste))
            ->assertRedirect();
        $this->assertSame($this->manager->id, $waste->fresh()->approved_by_user_id);
    }
}
