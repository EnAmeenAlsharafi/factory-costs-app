<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\SalesItemMapping;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SalesImportConfirmService;
use App\Services\Sales\SalesImportParserService;
use App\Services\Sales\SalesItemMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ReadyStockProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_preview_excludes_options_and_services_without_writing_products(): void
    {
        $this->signIn();

        $response = $this->upload([$this->row(), $this->row(['No.' => '22', 'النوع' => 'خيار']), $this->row(['No.' => '33', 'نوع المنتج' => 'خدمة حسب الطلب'])]);

        $response->assertOk()->assertViewHas('preview', fn (array $preview): bool => count($preview['rows']) === 1 && $preview['skipped'] === 2)->assertSee('1932559050');
        $this->assertDatabaseCount('materials', 0);
    }

    public function test_confirmation_imports_selected_catalog_fields_without_inventory_movements(): void
    {
        $this->signIn();
        $this->upload([$this->row(), $this->row(['No.' => '570779585', 'رمز المنتج sku' => 'OTHER-SKU'])]);

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_sku' => 'NP-210-2302-07', 'sales_price' => 250, 'sales_reference_cost' => 130, 'salla_stock_quantity' => 12, 'salla_status' => 'غير متاح', 'is_active' => true]);
        $this->assertDatabaseMissing('materials', ['salla_product_id' => '570779585']);
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        Storage::disk('local')->assertDirectoryEmpty('ready-stock-imports');
    }

    public function test_xlsx_catalog_preserves_numeric_product_number_and_sku(): void
    {
        $this->signIn();
        $row = $this->row(['No.' => 1932559050]);
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray([array_keys($row), array_values($row)]);
        $path = Storage::disk('local')->path('catalog.xlsx');
        (new Xlsx($workbook))->save($path);
        $workbook->disconnectWorksheets();
        $file = new UploadedFile($path, 'catalog.xlsx', null, null, true);

        $this->post(route('sales.ready-stock-products.import-upload'), ['import_file' => $file])->assertRedirect(route('sales.ready-stock-products.import-preview'));
        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_sku' => 'NP-210-2302-07']);
    }

    public function test_catalog_product_is_used_as_ready_stock_when_importing_an_order_by_sku(): void
    {
        $this->signIn();
        $this->upload([$this->row()]);
        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));
        $channel = SalesChannel::where('code', 'SADIR_STORE')->firstOrFail();
        Storage::disk('local')->put('order.csv', "رقم الطلب,اسم المنتج,SKU,الكمية,سعر المنتج قبل الخصم,طريقة الشحن\nTEST-CATALOG,مفرش جاهز,NP-210-2302-07,1,250,سدير\n");
        $batch = app(SalesImportParserService::class)->parse(Storage::disk('local')->path('order.csv'), 'order.csv', $channel, auth()->user());

        app(SalesImportConfirmService::class)->confirm($batch, auth()->user());

        $this->assertDatabaseHas('customer_orders', ['external_order_reference' => 'TEST-CATALOG', 'status' => 'APPROVED_FOR_FULFILLMENT']);
        $this->assertDatabaseHas('customer_order_lines', ['material_id' => Material::where('salla_product_id', '1932559050')->firstOrFail()->id, 'line_type' => 'READY_STOCK', 'is_incomplete' => false]);
    }

    public function test_reimport_updates_existing_product_by_id_after_sku_change(): void
    {
        $this->signIn();
        $material = $this->readyProduct(['salla_product_id' => '1932559050', 'sales_sku' => 'OLD-SKU']);
        $this->upload([$this->row()]);

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'code' => $material->code, 'sales_sku' => 'NP-210-2302-07']);
        $this->assertDatabaseCount('materials', 1);
    }

    public function test_existing_ready_product_is_linked_by_sku(): void
    {
        $this->signIn();
        $material = $this->readyProduct(['sales_sku' => 'NP-210-2302-07']);
        $this->upload([$this->row()]);

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'salla_product_id' => '1932559050']);
        $this->assertDatabaseCount('materials', 1);
    }

    public function test_conflicting_id_and_sku_block_import(): void
    {
        $this->signIn();
        $this->readyProduct(['salla_product_id' => '1932559050', 'sales_sku' => 'FIRST']);
        $this->readyProduct(['salla_product_id' => '570779585', 'sales_sku' => 'NP-210-2302-07']);
        $this->upload([$this->row()])->assertSee('تعارض بين رقم منتج سلة');

        $this->confirm([2])->assertSessionHasErrors('selected_rows');

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_sku' => 'FIRST']);
    }

    public function test_duplicate_file_identifiers_and_invalid_values_are_not_importable(): void
    {
        $this->signIn();
        $this->upload([$this->row(), $this->row(), $this->row(['No.' => '99', 'السعر المخفض' => '-10'])])->assertSee('رقم المنتج أو SKU مكرر داخل الملف.');

        $this->confirm([2])->assertSessionHasErrors('selected_rows');
        $this->confirm([4])->assertSessionHasErrors('selected_rows');
        $this->assertDatabaseCount('materials', 0);
    }

    public function test_missing_sku_does_not_reuse_the_product_number(): void
    {
        $this->signIn();
        $this->upload([$this->row(['رمز المنتج sku' => ''])])->assertSee('يلزم مطابقة يدوية');

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_sku' => null]);
    }

    public function test_order_file_is_rejected_and_temporary_upload_deleted(): void
    {
        $this->signIn();
        $file = UploadedFile::fake()->createWithContent('orders.csv', "رقم الطلب,اسم المنتج,SKU,الكمية\n100,مخدة,SKU-1,1\n");

        $this->post(route('sales.ready-stock-products.import-upload'), ['import_file' => $file])->assertSessionHasErrors('import_file');

        Storage::disk('local')->assertDirectoryEmpty('ready-stock-imports');
    }

    public function test_confirmation_requires_original_session_and_nonempty_selection(): void
    {
        $this->signIn();
        $this->upload([$this->row()]);

        $this->confirm([])->assertSessionHasErrors('selected_rows');
        $this->post(route('sales.ready-stock-products.import-confirm'), ['token' => 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa', 'selected_rows' => '[2]'])->assertSessionHasErrors('import_file');

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_catalog_sku_matches_orders_and_explicit_mapping_takes_precedence(): void
    {
        $material = $this->readyProduct(['sales_sku' => 'NP-210-2302-07']);
        $channel = SalesChannel::where('code', 'SADIR_STORE')->firstOrFail();
        $matcher = app(SalesItemMatchingService::class);

        $resolved = $matcher->resolve($channel, 'np-210-2302-07', 'مفرش جاهز');

        $this->assertSame('READY_STOCK', $resolved['target_type']);
        $this->assertSame($material->id, $resolved['mapping']->material_id);
        SalesItemMapping::create(['sales_channel_id' => $channel->id, 'match_type' => 'SKU', 'match_value' => 'np-210-2302-07', 'target_type' => 'IGNORE', 'is_active' => true]);
        $this->assertSame('IGNORE', $matcher->resolve($channel, 'NP-210-2302-07', null)['target_type']);
    }

    public function test_inactive_and_ambiguous_catalog_skus_are_not_matched(): void
    {
        $this->readyProduct(['sales_sku' => 'DUPLICATE']);
        $this->readyProduct(['sales_sku' => 'DUPLICATE']);
        $this->readyProduct(['sales_sku' => 'INACTIVE', 'is_active' => false]);
        $channel = SalesChannel::where('code', 'SADIR_STORE')->firstOrFail();

        $matcher = app(SalesItemMatchingService::class);

        $this->assertNull($matcher->resolve($channel, 'DUPLICATE', null)['target_type']);
        $this->assertNull($matcher->resolve($channel, 'INACTIVE', null)['target_type']);
    }

    public function test_import_saves_and_displays_only_the_first_product_image(): void
    {
        $this->signIn();
        $first = 'https://cdn.salla.sa/store/first.webp';
        $second = 'https://cdn.salla.sa/store/second.jpg';
        $response = $this->upload([$this->row(['صورة المنتج' => '  '.$first.', '.$second.'  '])]);
        $response->assertSee($first)->assertDontSee($second);
        $this->assertDatabaseCount('materials', 0);

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_image_url' => $first]);
        $this->get(route('sales.ready-stock-products.index'))->assertSee($first)->assertDontSee($second);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_reimport_without_an_image_keeps_the_existing_main_image(): void
    {
        $this->signIn();
        $material = $this->readyProduct(['salla_product_id' => '1932559050', 'sales_sku' => 'NP-210-2302-07', 'sales_image_url' => 'https://cdn.salla.sa/store/original.png']);
        $this->upload([$this->row()]);

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertSame('https://cdn.salla.sa/store/original.png', $material->fresh()->sales_image_url);
    }

    public function test_an_invalid_first_image_is_rejected_instead_of_importing_a_later_image(): void
    {
        $this->signIn();
        $this->upload([$this->row(['صورة المنتج' => 'javascript:alert(1),https://cdn.salla.sa/store/second.jpg'])])
            ->assertViewHas('preview', fn (array $preview): bool => $preview['rows'][2]['error'] !== null);

        $this->confirm([2])->assertSessionHasErrors();

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_discounted_price_is_used_and_hidden_or_zero_quantity_products_are_excluded(): void
    {
        $this->signIn();
        $this->upload([
            $this->row(['سعر المنتج' => '900', 'السعر المخفض' => '525']),
            $this->row(['No.' => '22', 'حالة المنتج' => 'مخفي']),
            $this->row(['No.' => '33', 'الكمية المتوفرة' => '0']),
        ])->assertViewHas('preview', fn (array $preview): bool => count($preview['rows']) === 1 && $preview['skipped'] === 2 && $preview['rows'][2]['sales_price'] === '525');

        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));

        $this->assertDatabaseHas('materials', ['salla_product_id' => '1932559050', 'sales_price' => 525]);
        $this->assertDatabaseCount('materials', 1);
    }

    public function test_excluded_existing_products_are_not_updated_even_when_their_row_is_submitted(): void
    {
        $this->signIn();
        $material = $this->readyProduct(['salla_product_id' => '1932559050', 'sales_sku' => 'NP-210-2302-07', 'sales_price' => 600, 'salla_stock_quantity' => 8]);
        $this->upload([$this->row(['حالة المنتج' => 'مخفية'])])
            ->assertViewHas('preview', fn (array $preview): bool => $preview['rows'] === [] && $preview['skipped'] === 1);

        $this->confirm([2])->assertSessionHasErrors('selected_rows');

        $this->assertSame('600.0000', $material->fresh()->sales_price);
        $this->assertSame('8.0000', $material->fresh()->salla_stock_quantity);
    }

    public function test_missing_discounted_price_does_not_fall_back_to_original_price(): void
    {
        $this->signIn();
        $this->upload([$this->row(['السعر المخفض' => '', 'سعر المنتج' => '900'])])->assertSee('السعر المخفض مفقود');

        $this->confirm([2])->assertSessionHasErrors('selected_rows');

        $this->assertDatabaseCount('materials', 0);
    }

    protected function signIn(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role_id' => Role::where('name', 'admin')->firstOrFail()->id, 'is_active' => true]));
    }

    public function test_option_products_import_as_parents_and_local_options_can_be_added_for_manual_orders(): void
    {
        $this->signIn();
        $this->upload([$this->row(), $this->row(['No.' => '3322', 'النوع' => 'خيار', 'رمز المنتج sku' => 'EXTERNAL-OPTION'])]);
        $this->confirm([2])->assertRedirect(route('sales.ready-stock-products.index'));
        $parent = Material::where('salla_product_id', '1932559050')->firstOrFail();

        $this->assertTrue($parent->has_ready_options);
        $this->assertSame(0, $parent->readyOptions()->count());
        $this->assertDatabaseMissing('materials', ['sales_sku' => 'EXTERNAL-OPTION']);
        $this->get(route('sales.ready-stock-products.edit', $parent))->assertSee('إدارة الخيارات / المقاسات');
        $this->get(route('sales.ready-stock-products.options.create', $parent))->assertOk();
        $this->post(route('sales.ready-stock-products.store'), [
            'name_ar' => 'مقاس محلي', 'ready_parent_id' => $parent->id, 'ready_option_label' => '160×200',
            'sales_sku' => 'LOCAL-160', 'base_unit_id' => $parent->base_unit_id, 'sales_price' => 300, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $option = $parent->readyOptions()->firstOrFail();
        $this->assertSame('مفرش جاهز — 160×200', $option->sales_display_name);
        $this->assertFalse($option->has_ready_options);
        $this->getJson('/api/search/ready-products?q=NP-210-2302-07')->assertJsonPath('0.id', $parent->id)->assertJsonPath('0.has_ready_options', true);
        $this->getJson('/api/search/ready-products?q=LOCAL-160')->assertJsonPath('0.id', $option->id);
        $this->put(route('sales.ready-stock-products.update', $parent), [
            'name_ar' => $parent->name_ar, 'base_unit_id' => $parent->base_unit_id, 'is_active' => 1, 'has_ready_options' => 0,
        ])->assertSessionHasErrors('has_ready_options');
        $this->post(route('sales.orders.store'), [
            'customer_id' => Customer::firstOrFail()->id, 'sales_channel_id' => SalesChannel::firstOrFail()->id,
            'order_date' => '2026-11-05', 'priority' => 'NORMAL',
            'lines' => [['line_type' => 'READY_STOCK', 'material_id' => $parent->id, 'quantity' => 1, 'unit_price' => 250]],
        ])->assertSessionHasErrors('lines.0.material_id');
    }

    /** @param array<string, mixed> $overrides */
    protected function readyProduct(array $overrides): Material
    {
        return Material::create(array_merge(['code' => Material::generateNextCode(), 'name_ar' => 'منتج قديم', 'material_category_id' => MaterialCategory::where('code', 'READY_GOODS')->firstOrFail()->id, 'base_unit_id' => UnitOfMeasure::where('code', 'PIECE')->firstOrFail()->id, 'is_active' => true, 'is_sellable_ready_product' => true], $overrides));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function row(array $overrides = []): array
    {
        return array_merge(['No.' => '1932559050', 'النوع' => 'منتج', 'أسم المنتج' => 'مفرش جاهز', 'نوع المنتج' => 'منتج جاهز', 'رمز المنتج sku' => 'NP-210-2302-07', 'تصنيف المنتج' => 'مفارش', 'سعر المنتج' => '400', 'السعر المخفض' => '250', 'سعر التكلفة' => '130', 'الكمية المتوفرة' => '12', 'حالة المنتج' => 'غير متاح'], $overrides);
    }

    /** @param array<int, array<string, mixed>> $rows */
    protected function upload(array $rows): TestResponse
    {
        $stream = fopen('php://memory', 'r+');
        fputcsv($stream, array_keys($rows[0]), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $response = $this->post(route('sales.ready-stock-products.import-upload'), ['import_file' => UploadedFile::fake()->createWithContent('products.csv', $contents)]);
        $response->assertRedirect(route('sales.ready-stock-products.import-preview'));

        return $this->get(route('sales.ready-stock-products.import-preview'));
    }

    /** @param array<int, int> $rows */
    protected function confirm(array $rows): TestResponse
    {
        return $this->post(route('sales.ready-stock-products.import-confirm'), ['token' => session('ready_stock_import.token'), 'selected_rows' => json_encode($rows)]);
    }
}
