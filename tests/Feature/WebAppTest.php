<?php

namespace Tests\Feature;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manifest_describes_an_installable_arabic_app_from_company_settings(): void
    {
        CompanySetting::current()->update(['name_ar' => 'مصنع مفروشات سدير', 'primary_color' => '#0f766e']);

        $response = $this->get(route('webapp.manifest'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJson([
                'name' => 'مصنع مفروشات سدير',
                'short_name' => 'مفروشات سدير',
                'lang' => 'ar',
                'dir' => 'rtl',
                'display' => 'standalone',
                'theme_color' => '#0f766e',
            ]);
        $this->assertCount(3, $response->json('icons'));
        $this->assertSame('maskable', $response->json('icons.2.purpose'));
    }

    public function test_home_screen_icons_are_png_in_the_supported_sizes_only(): void
    {
        foreach ([[192, 'any'], [512, 'any'], [512, 'maskable']] as [$size, $purpose]) {
            $response = $this->get(route('webapp.icon', ['size' => $size, 'purpose' => $purpose]));
            $response->assertOk()->assertHeader('Content-Type', 'image/png');
            [$width, $height] = getimagesize($response->getFile()->getPathname());
            $this->assertSame([$size, $size], [$width, $height]);
        }

        $this->get(route('webapp.icon', ['size' => 64]))->assertNotFound();
    }

    public function test_pages_link_the_manifest_and_offline_page_works_without_login(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('webapp.manifest'), false);
        $this->actingAs(User::where('username', 'admin')->firstOrFail())->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('webapp.manifest'), false)
            ->assertSee('ثبّت النظام كتطبيق على جوالك');
        $this->get(route('webapp.offline'))->assertOk()->assertSee('لا يوجد اتصال بالإنترنت');
    }
}
