<?php

namespace Tests\Feature;

use App\Models\CustomerOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Production\ProductionMaterialRequestTest;

class Stage105RuntimeRegressionTest extends ProductionMaterialRequestTest
{
    public function test_static_recipe_routes_are_not_shadowed_by_recipe_binding(): void
    {
        $templates = Route::getRoutes()->match(Request::create('/recipes/templates', 'GET'));
        $components = Route::getRoutes()->match(Request::create('/recipes/components', 'GET'));

        $this->assertSame('recipes.templates.index', $templates->getName());
        $this->assertSame('recipes.components.index', $components->getName());
    }

    public function test_confirmed_runtime_pages_render_for_authorized_users(): void
    {
        $this->actingAs($this->manager)->get(route('recipes.templates.index'))->assertOk();
        $this->get(route('recipes.components.index'))->assertOk();
        $this->get(route('production.queue.index'))->assertOk();
        $this->get(route('production.material-requests.create', [
            'production_order_id' => $this->productionOrder->id,
        ]))->assertOk()->assertSee('طلب خامات');

        $order = CustomerOrder::factory()->create([
            'status' => 'PENDING_PRODUCTION_REVIEW',
            'created_by_user_id' => $this->manager->id,
        ]);
        $this->get(route('sales.orders.review', $order))->assertOk();

        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'is_active' => true,
        ]);
        $this->actingAs($admin)->get(route('inventory.adjustments.index'))->assertOk();
        $this->get(route('inventory.adjustments.create'))->assertOk();
    }

    public function test_core_authorized_get_pages_smoke_without_server_errors(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'is_active' => true,
        ]);
        $this->actingAs($admin);

        $routes = [
            'dashboard',
            'users.index',
            'roles.index',
            'customers.index',
            'suppliers.index',
            'materials.index',
            'inventory.balances.index',
            'inventory.receipts.index',
            'inventory.receipts.create',
            'inventory.issues.index',
            'inventory.issues.create',
            'inventory.returns.index',
            'inventory.returns.create',
            'products.models.index',
            'recipes.index',
            'recipes.templates.index',
            'recipes.components.index',
            'sales.quotations.index',
            'sales.orders.index',
            'production.orders.index',
            'production.queue.index',
            'production.board.index',
            'production.routings.index',
            'production.material-requests.index',
            'production.quality-incidents.index',
            'production.rework.index',
            'production.reports.index',
            'production.reports.cost',
            'production.reports.waste',
            'production.reports.quality',
            'inventory.adjustments.index',
        ];

        foreach ($routes as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }
}
