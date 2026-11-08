<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_administrator_updates_company_identity_and_logo(): void
    {
        Storage::fake('public');
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('settings.company.edit'))->assertOk()->assertSee('إعدادات الشركة');

        $this->actingAs($admin)->put(route('settings.company.update'), [
            'name_ar' => 'مصنع سدير للمفروشات',
            'name_en' => 'Sudair Furniture Factory',
            'primary_color' => '#0f766e',
            'secondary_color' => '#f59e0b',
            'vat_number' => '300000000000003',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ])->assertRedirect(route('settings.company.edit'))->assertSessionHasNoErrors();

        $company = CompanySetting::sole();
        $this->assertSame('مصنع سدير للمفروشات', $company->name_ar);
        $this->assertSame('#0f766e', $company->primary_color);
        Storage::disk('public')->assertExists($company->logo_path);

        $this->actingAs($admin)->put(route('settings.company.update'), [
            'name_ar' => 'مصنع سدير للمفروشات',
            'primary_color' => '#0f766e',
            'secondary_color' => '#f59e0b',
            'remove_logo' => 1,
        ]);
        Storage::disk('public')->assertMissing($company->logo_path);
        $this->assertNull($company->fresh()->logo_path);
    }

    public function test_logo_is_served_by_the_app_and_missing_logo_is_not_found(): void
    {
        Storage::fake('public');
        $this->get(route('company.logo'))->assertNotFound();

        Storage::disk('public')->put('company/logo.png', UploadedFile::fake()->image('logo.png')->getContent());
        CompanySetting::current()->update(['logo_path' => 'company/logo.png']);

        $this->get(route('company.logo'))->assertOk();
        $this->assertStringContainsString(route('company.logo'), CompanySetting::current()->logo_url);
    }

    public function test_settings_reject_invalid_colour_and_non_administrators(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $this->actingAs($admin)->put(route('settings.company.update'), [
            'name_ar' => 'مصنع',
            'primary_color' => 'blue',
            'secondary_color' => '#f59e0b',
        ])->assertSessionHasErrors('primary_color');

        $buyer = User::factory()->create(['role_id' => Role::where('name', 'purchasing_user')->first()->id, 'is_active' => true]);
        $this->actingAs($buyer)->get(route('settings.company.edit'))->assertForbidden();
        $this->actingAs($buyer)->put(route('settings.company.update'), ['name_ar' => 'x', 'primary_color' => '#000000', 'secondary_color' => '#000000'])->assertForbidden();
    }
}
