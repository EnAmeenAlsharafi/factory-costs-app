<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\Role;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerOrderDesignImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_custom_design_image_is_saved_privately_and_shown_with_the_order(): void
    {
        $user = $this->admin();
        $payload = $this->payload();
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('design.png');
        $payload['lines'][0]['custom_design_image_path'] = 'forged.png';

        $this->actingAs($user)->post(route('sales.orders.store'), $payload)->assertSessionHasNoErrors();

        $line = CustomerOrderLine::latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($line->custom_design_image_path);
        $this->assertStringStartsWith('customer-order-designs/', $line->custom_design_image_path);
        $url = route('sales.orders.design-image', [$line->customerOrder, $line]);
        $this->get(route('sales.orders.show', $line->customerOrder))->assertSee($url);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('sales.orders.edit', $line->customerOrder))->assertSee('multipart/form-data', false)->assertSee('custom_design_image_url', false);
    }

    public function test_edit_without_upload_keeps_the_image_and_new_upload_replaces_it(): void
    {
        $payload = $this->payload();
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('first.png');
        $this->actingAs($this->admin())->post(route('sales.orders.store'), $payload)->assertSessionHasNoErrors();
        $line = CustomerOrderLine::latest('id')->firstOrFail();
        $originalPath = $line->custom_design_image_path;
        unset($payload['lines'][0]['custom_design_image']);
        $payload['lines'][0]['id'] = $line->id;

        $this->put(route('sales.orders.update', $line->customerOrder), $payload)->assertSessionHasNoErrors();
        $this->assertSame($originalPath, $line->fresh()->custom_design_image_path);
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('replacement.jpg');
        $this->put(route('sales.orders.update', $line->customerOrder), $payload)->assertSessionHasNoErrors();

        $replacementPath = $line->fresh()->custom_design_image_path;
        $this->assertNotSame($originalPath, $replacementPath);
        Storage::disk('local')->assertExists($replacementPath);
    }

    public function test_invalid_or_oversized_files_and_images_for_normal_lines_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->create('document.pdf', 20, 'application/pdf');
        $this->actingAs($this->admin())->post(route('sales.orders.store'), $payload)->assertSessionHasErrors('lines.0.custom_design_image');
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('large.jpg')->size(5121);
        $this->post(route('sales.orders.store'), $payload)->assertSessionHasErrors('lines.0.custom_design_image');
        $payload['lines'][0]['custom_design'] = false;
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('not-custom.png');
        $this->post(route('sales.orders.store'), $payload)->assertSessionHasErrors('lines.0.custom_design_image');

        $this->assertDatabaseCount('customer_order_lines', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_images_require_order_permission_and_cannot_be_requested_under_another_order(): void
    {
        $order = CustomerOrder::factory()->create();
        $line = CustomerOrderLine::factory()->create([
            'customer_order_id' => $order->id, 'custom_design' => true,
            'custom_design_image_path' => 'customer-order-designs/private.png',
        ]);
        Storage::disk('local')->put($line->custom_design_image_path, 'private');
        $url = route('sales.orders.design-image', [$order, $line]);
        $this->get($url)->assertRedirect(route('login'));
        $worker = User::factory()->create(['role_id' => Role::where('name', 'production_worker')->value('id'), 'is_active' => true]);
        $this->actingAs($worker)->get($url)->assertForbidden();
        $anotherOrder = CustomerOrder::factory()->create();

        $this->actingAs($this->admin())->get(route('sales.orders.design-image', [$anotherOrder, $line]))->assertNotFound();
    }

    public function test_failed_update_removes_the_new_upload_without_changing_the_existing_image(): void
    {
        $order = CustomerOrder::factory()->create(['status' => 'CANCELLED']);
        $line = CustomerOrderLine::factory()->create([
            'customer_order_id' => $order->id, 'custom_design' => true,
            'custom_design_image_path' => 'customer-order-designs/existing.png',
        ]);
        Storage::disk('local')->put($line->custom_design_image_path, 'existing');
        $payload = $this->payload();
        $payload['lines'][0]['id'] = $line->id;
        $payload['lines'][0]['custom_design_image'] = UploadedFile::fake()->image('unsaved.png');

        $this->actingAs($this->admin())->put(route('sales.orders.update', $order), $payload)->assertSessionHas('error');

        $this->assertSame('customer-order-designs/existing.png', $line->fresh()->custom_design_image_path);
        $this->assertSame(['customer-order-designs/existing.png'], Storage::disk('local')->allFiles());
    }

    protected function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'admin')->value('id'), 'is_active' => true]);
    }

    /** @return array<string, mixed> */
    protected function payload(): array
    {
        return [
            'customer_id' => Customer::firstOrFail()->id,
            'sales_channel_id' => SalesChannel::firstOrFail()->id,
            'order_date' => '2026-11-05', 'priority' => 'NORMAL',
            'lines' => [[
                'line_type' => 'MANUFACTURED', 'custom_design' => true, 'custom_design_name' => 'تصميم العميل',
                'requested_width_cm' => 160, 'requested_length_cm' => 200, 'quantity' => 1, 'unit_price' => 800,
            ]],
        ];
    }
}
