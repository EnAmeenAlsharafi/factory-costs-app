<?php

namespace Tests\Feature\MasterData;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_suppliers_index(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('suppliers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('suppliers.index');
    }

    public function test_admin_can_create_supplier_with_auto_generated_code(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('suppliers.store'), [
            'name' => 'شركة الرياض لصناعة الأخشاب',
            'commercial_name' => 'أخشاب الرياض',
            'contact_person' => 'أحمد المهندس',
            'mobile' => '0559988776',
            'city' => 'الرياض',
            'tax_number' => '310123456700003',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'supplier_code' => 'SUP-000001',
            'name' => 'شركة الرياض لصناعة الأخشاب',
            'city' => 'الرياض',
            'tax_number' => '310123456700003',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_supplier_details(): void
    {
        $admin = User::where('username', 'admin')->first();
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-000001',
            'name' => 'مورد تجريبي للأقمشة',
            'contact_person' => 'فيصل',
            'mobile' => '0501122334',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('suppliers.show', $supplier));

        $response->assertStatus(200);
        $response->assertViewIs('suppliers.show');
        $response->assertSee('مورد تجريبي للأقمشة');
        $response->assertSee('SUP-000001');
    }

    public function test_admin_can_update_supplier(): void
    {
        $admin = User::where('username', 'admin')->first();
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-000001',
            'name' => 'مورد قديم',
            'mobile' => '0500000000',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('suppliers.update', $supplier), [
            'name' => 'مورد محدث للأجهزة والبراغي',
            'mobile' => '0599999999',
            'city' => 'الدمام',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'مورد محدث للأجهزة والبراغي',
            'city' => 'الدمام',
        ]);
    }

    public function test_admin_can_toggle_supplier_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $supplier = Supplier::create([
            'supplier_code' => 'SUP-000001',
            'name' => 'مورد الحالة',
            'is_active' => true,
        ]);

        $this->assertTrue($supplier->is_active);

        // Deactivate
        $response = $this->actingAs($admin)->post(route('suppliers.toggle-status', $supplier));
        $response->assertRedirect();
        $this->assertFalse($supplier->fresh()->is_active);

        // Reactivate
        $response = $this->actingAs($admin)->post(route('suppliers.toggle-status', $supplier));
        $response->assertRedirect();
        $this->assertTrue($supplier->fresh()->is_active);
    }
}
