<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SalesItemMapping;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SallaValueNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesItemMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $unauthorizedUser;

    protected Material $readyMaterial;

    protected SalesChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id, 'is_active' => true]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);
        $this->channel = SalesChannel::where('code', 'SADIR_STORE')->first();

        $this->readyMaterial = Material::create([
            'code' => 'MAT-MAPPING-READY-0001',
            'name_ar' => 'منتج اختبار المطابقة',
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_view_mappings(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('sales.item-mappings.index'));

        $response->assertForbidden();
    }

    public function test_storing_a_sku_mapping_normalizes_the_match_value(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sales.item-mappings.store'), [
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => '  RDY-TEST-002  ',
            'target_type' => 'READY_STOCK',
            'material_id' => $this->readyMaterial->id,
        ]);

        $response->assertRedirect(route('sales.item-mappings.index'));
        $mapping = SalesItemMapping::where('material_id', $this->readyMaterial->id)->first();
        $this->assertNotNull($mapping);
        $this->assertEquals(SallaValueNormalizer::normalizeMatchValue('  RDY-TEST-002  '), $mapping->match_value);
        $this->assertEquals($this->admin->id, $mapping->created_by_user_id);
    }

    public function test_ready_stock_mapping_without_material_id_fails_validation(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sales.item-mappings.store'), [
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-missing-target',
            'target_type' => 'READY_STOCK',
        ]);

        $response->assertSessionHasErrors('material_id');
        $this->assertDatabaseMissing('sales_item_mappings', ['match_value' => 'rdy-missing-target']);
    }

    public function test_updating_a_mapping_changes_its_target(): void
    {
        $mapping = SalesItemMapping::create([
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-update-test',
            'target_type' => 'READY_STOCK',
            'material_id' => $this->readyMaterial->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('sales.item-mappings.update', $mapping), [
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-update-test',
            'target_type' => 'READY_STOCK',
            'material_id' => $this->readyMaterial->id,
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('sales.item-mappings.index'));
        $this->assertFalse($mapping->fresh()->is_active);
    }
}
