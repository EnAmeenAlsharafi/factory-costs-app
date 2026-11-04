<?php

namespace Tests\Feature\Sales;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SalesImportBatch;
use App\Models\SalesItemMapping;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SalesImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $unauthorizedUser;

    protected SalesChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role_id' => Role::where('name', 'admin')->first()->id, 'is_active' => true]);
        $this->unauthorizedUser = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->first()->id, 'is_active' => true]);
        $this->channel = SalesChannel::where('code', 'SADIR_STORE')->first();

        $readyMaterial = Material::create([
            'code' => 'MAT-IMPORT-CTRL-READY-0001',
            'name_ar' => 'مرتبة اختبار الرفع',
            'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->first()->id,
            'base_unit_id' => UnitOfMeasure::first()->id,
            'is_active' => true,
            'is_sellable_ready_product' => true,
        ]);

        SalesItemMapping::create([
            'sales_channel_id' => $this->channel->id,
            'match_type' => 'SKU',
            'match_value' => 'rdy-test-001',
            'target_type' => 'READY_STOCK',
            'material_id' => $readyMaterial->id,
            'is_active' => true,
        ]);
    }

    protected function fixtureUpload(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/salla_sample_orders.csv'),
            'salla_sample_orders.csv',
            'text/csv',
            null,
            true
        );
    }

    public function test_unauthorized_user_cannot_upload_a_file(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->post(route('sales.imports.store'), [
            'sales_channel_id' => $this->channel->id,
            'import_file' => $this->fixtureUpload(),
        ]);

        $response->assertForbidden();
    }

    public function test_uploading_a_valid_file_redirects_to_preview(): void
    {
        $response = $this->actingAs($this->admin)->post(route('sales.imports.store'), [
            'sales_channel_id' => $this->channel->id,
            'import_file' => $this->fixtureUpload(),
        ]);

        $batch = SalesImportBatch::first();
        $this->assertNotNull($batch);
        $this->assertEquals('UPLOADED', $batch->status);
        $this->assertEquals(6, $batch->rows()->count());
        $response->assertRedirect(route('sales.imports.preview', $batch));
    }

    public function test_preview_page_shows_import_and_skip_decisions_without_creating_orders(): void
    {
        $batch = $this->actingAs($this->admin)
            ->post(route('sales.imports.store'), [
                'sales_channel_id' => $this->channel->id,
                'import_file' => $this->fixtureUpload(),
            ]);

        $createdBatch = SalesImportBatch::first();

        $response = $this->actingAs($this->admin)->get(route('sales.imports.preview', $createdBatch));

        $response->assertOk();
        $response->assertSee('SALLA-1001');
        $this->assertDatabaseCount('customer_orders', 0);
    }

    public function test_confirming_a_batch_creates_orders_and_redirects_to_result(): void
    {
        $this->actingAs($this->admin)->post(route('sales.imports.store'), [
            'sales_channel_id' => $this->channel->id,
            'import_file' => $this->fixtureUpload(),
        ]);
        $batch = SalesImportBatch::first();

        $response = $this->actingAs($this->admin)->post(route('sales.imports.confirm', $batch));

        $response->assertRedirect(route('sales.imports.result', $batch));
        $this->assertGreaterThan(0, $batch->fresh()->created_count);
    }
}
