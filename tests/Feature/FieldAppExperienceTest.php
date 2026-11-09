<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\DeliveryOrder;
use App\Models\Department;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FieldAppExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_driver_gets_call_whatsapp_and_map_shortcuts_and_bottom_tabs(): void
    {
        $driver = User::factory()->create(['role_id' => Role::where('name', 'delivery_user')->first()->id, 'is_active' => true]);
        $order = CustomerOrder::create([
            'order_number' => 'ORD-2026-007001',
            'customer_id' => Customer::first()->id,
            'sales_channel_id' => SalesChannel::first()->id,
            'status' => 'APPROVED_FOR_PRODUCTION',
            'order_date' => now(),
            'total_amount' => 1000,
            'created_by_user_id' => $driver->id,
        ]);
        DeliveryOrder::create([
            'delivery_number' => 'DEL-2026-7001',
            'customer_order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'customer_name_snapshot' => 'عبدالرحمن',
            'customer_phone_snapshot' => '0551234567',
            'city_snapshot' => 'الرياض',
            'district_snapshot' => 'النرجس',
            'location_notes' => 'الموقع: https://maps.app.goo.gl/abc123',
            'assigned_user_id' => $driver->id,
            'status' => 'ASSIGNED',
            'created_by_user_id' => $driver->id,
        ]);

        $this->actingAs($driver)->get(route('delivery.orders.my-tasks'))
            ->assertOk()
            ->assertSee('https://wa.me/966551234567', false)
            ->assertSee('https://maps.app.goo.gl/abc123', false)
            ->assertSee('tel:0551234567', false)
            ->assertSee('field-tabbar', false)
            ->assertSee('توصيلاتي');
    }

    public function test_worker_gets_bottom_tabs_but_office_staff_do_not(): void
    {
        $worker = User::factory()->create([
            'role_id' => Role::where('name', 'production_worker')->first()->id,
            'department_id' => Department::where('code', 'CARPENTRY')->first()->id,
            'is_active' => true,
        ]);

        $this->actingAs($worker)->get(route('production.my-tasks'))->assertOk()->assertSee('field-tabbar', false)->assertSee('أعمال قسمي');
        $this->actingAs(User::where('username', 'admin')->firstOrFail())->get(route('dashboard'))->assertOk()->assertDontSee('field-tabbar', false);
    }

    public function test_validation_messages_are_in_arabic(): void
    {
        $this->assertSame('حقل الكمية مطلوب.', Validator::make([], ['quantity' => 'required'])->errors()->first('quantity'));
    }
}
