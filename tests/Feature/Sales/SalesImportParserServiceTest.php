<?php

namespace Tests\Feature\Sales;

use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use App\Services\Sales\SalesImportParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesImportParserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function fixturePath(): string
    {
        return base_path('tests/Fixtures/salla_sample_orders.csv');
    }

    public function test_parses_header_by_name_and_groups_rows_by_order_reference(): void
    {
        $this->seed();
        $channel = SalesChannel::where('code', 'SADIR_STORE')->first();
        $uploader = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id]);

        $batch = app(SalesImportParserService::class)->parse($this->fixturePath(), 'salla_sample_orders.csv', $channel, $uploader);

        $this->assertEquals('UPLOADED', $batch->status);
        $this->assertEquals($channel->id, $batch->sales_channel_id);
        $this->assertCount(6, $batch->rows);

        $row1 = $batch->rows->firstWhere('external_order_reference', 'SALLA-1001');
        $this->assertNotNull($row1);
        $this->assertCount(1, $row1->raw_data['lines']);
        $this->assertEquals('مرتبة طبية فاخرة اختبار', $row1->raw_data['lines'][0]['product_name']);
    }

    public function test_strips_quotes_and_converts_placeholder_to_null(): void
    {
        $this->seed();
        $channel = SalesChannel::where('code', 'SADIR_STORE')->first();
        $uploader = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id]);

        $batch = app(SalesImportParserService::class)->parse($this->fixturePath(), 'salla_sample_orders.csv', $channel, $uploader);

        $row1 = $batch->rows->firstWhere('external_order_reference', 'SALLA-1001');
        $this->assertEquals('1673290001', $row1->raw_data['external_order_internal_id']);
        $this->assertNull($row1->raw_data['map_url']);
    }

    public function test_normalizes_mobile_number_to_local_format(): void
    {
        $this->seed();
        $channel = SalesChannel::where('code', 'SADIR_STORE')->first();
        $uploader = User::factory()->create(['role_id' => Role::where('name', 'customer_service')->first()->id]);

        $batch = app(SalesImportParserService::class)->parse($this->fixturePath(), 'salla_sample_orders.csv', $channel, $uploader);

        $row1 = $batch->rows->firstWhere('external_order_reference', 'SALLA-1001');
        $this->assertEquals('0591000001', $row1->raw_data['customer_mobile']);
    }
}
