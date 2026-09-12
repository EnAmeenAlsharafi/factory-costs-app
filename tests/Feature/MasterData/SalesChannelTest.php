<?php

namespace Tests\Feature\MasterData;

use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_sales_channels_list_and_seeds_exist(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('sales-channels.index'));

        $response->assertStatus(200);
        $response->assertViewIs('sales-channels.index');
        $response->assertSee('SADIR_STORE');
        $response->assertSee('WHOLESALE');
        $response->assertSee('DIRECT');
        $response->assertSee('CUSTOM');
    }

    public function test_admin_can_create_new_sales_channel(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('sales-channels.store'), [
            'code' => 'EXHIBITION',
            'name_ar' => 'معارض ومؤتمرات الضيافة',
            'name_en' => 'Hospitality Exhibitions',
            'description' => 'توريد أثاث ومفروشات معارض الفنادق',
            'sort_order' => 5,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('sales-channels.index'));
        $this->assertDatabaseHas('sales_channels', [
            'code' => 'EXHIBITION',
            'name_ar' => 'معارض ومؤتمرات الضيافة',
            'sort_order' => 5,
        ]);
    }

    public function test_admin_can_update_sales_channel(): void
    {
        $admin = User::where('username', 'admin')->first();
        $channel = SalesChannel::where('code', 'DIRECT')->first();

        $response = $this->actingAs($admin)->put(route('sales-channels.update', $channel), [
            'code' => 'DIRECT',
            'name_ar' => 'البيع المباشر للأفراد والمستهلكين',
            'sort_order' => 2,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('sales-channels.index'));
        $this->assertDatabaseHas('sales_channels', [
            'id' => $channel->id,
            'name_ar' => 'البيع المباشر للأفراد والمستهلكين',
        ]);
    }

    public function test_admin_can_toggle_sales_channel_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $channel = SalesChannel::where('code', 'CUSTOM')->first();

        $this->assertTrue($channel->is_active);

        // Deactivate
        $this->actingAs($admin)->post(route('sales-channels.toggle-status', $channel));
        $this->assertFalse($channel->fresh()->is_active);

        // Reactivate
        $this->actingAs($admin)->post(route('sales-channels.toggle-status', $channel));
        $this->assertTrue($channel->fresh()->is_active);
    }
}
