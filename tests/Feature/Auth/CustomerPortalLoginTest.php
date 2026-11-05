<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_login_has_its_own_branding_and_no_staff_demo_accounts(): void
    {
        $this->get(route('portal.login'))->assertOk()->assertSee('بوابة العملاء والمعارض')
            ->assertSee(route('portal.login.store'))->assertDontSee('admin123')->assertDontSee('prod.manager')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_active_customer_can_log_in_and_is_always_sent_to_their_portal(): void
    {
        $user = $this->customerUser();

        $this->withSession(['url.intended' => route('users.index')])->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'password', 'remember' => 1])->assertRedirect(route('portal.home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get(route('portal.login'))->assertRedirect(route('portal.home'));
        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_staff_credentials_cannot_authenticate_through_customer_login(): void
    {
        $this->post(route('portal.login.store'), ['username' => 'admin', 'password' => 'admin123'])->assertRedirect(route('portal.login'))->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_and_unlinked_customer_accounts_are_refused(): void
    {
        $user = $this->customerUser(['is_active' => false]);
        $this->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $user = $this->customerUser(['customer_id' => null]);
        $this->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_customer_record_cannot_sign_in(): void
    {
        $user = $this->customerUser();
        $user->customer->update(['is_active' => false]);

        $this->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_invalid_credentials_are_throttled_without_authenticating(): void
    {
        $user = $this->customerUser();
        $key = Str::transliterate(Str::lower($user->username).'|127.0.0.1');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        $this->post(route('portal.login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_login_requires_username_and_password_without_flashing_password(): void
    {
        $this->post(route('portal.login.store'), [])->assertSessionHasErrors(['username', 'password']);
        $this->post(route('portal.login.store'), ['username' => 'unknown.customer', 'password' => 'private-password'])->assertSessionHasErrors('username')->assertSessionMissing('_old_input.password');
    }

    public function test_customer_logout_returns_to_customer_login_and_ends_the_session(): void
    {
        $this->actingAs($this->customerUser())->post(route('portal.logout'))->assertRedirect(route('portal.login'));

        $this->assertGuest();
        $this->get(route('portal.orders.index'))->assertRedirect(route('portal.login'));
    }

    public function test_legacy_logout_and_deactivation_keep_customer_in_their_login_flow(): void
    {
        $this->actingAs($this->customerUser())->post(route('logout'))->assertRedirect(route('portal.login'));
        $user = $this->customerUser(['is_active' => false]);

        $this->actingAs($user)->get(route('portal.home'))->assertRedirect(route('portal.login'));

        $this->assertGuest();
    }

    public function test_staff_session_is_preserved_until_staff_explicitly_logs_out(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('portal.login'))->assertSee('يوجد حساب موظف')->assertDontSee('name="password"', false);
        $this->post(route('portal.login.store'), ['username' => 'unused', 'password' => 'unused'])->assertRedirect(route('portal.login'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_portal_subdomain_root_and_login_are_customer_entry_points(): void
    {
        $this->get('https://mr.sadirfactory.com/')->assertRedirect('https://mr.sadirfactory.com/portal/login');
        $this->get('https://mr.sadirfactory.com/login')->assertRedirect('https://mr.sadirfactory.com/portal/login');
        $this->get('https://mr.sadirfactory.com/portal/login')->assertOk()->assertSee('تسجيل دخول العميل');
        $this->get('https://mr.sadirfactory.com/portal/account')->assertRedirect('https://mr.sadirfactory.com/portal/login');
    }

    public function test_portal_subdomain_does_not_expose_internal_pages_or_staff_login(): void
    {
        $this->get('https://mr.sadirfactory.com/users')->assertNotFound();
        $this->get('https://mr.sadirfactory.com/sales/portal-requests')->assertNotFound();
        $this->post('https://mr.sadirfactory.com/login', ['username' => 'admin', 'password' => 'admin123'])->assertNotFound();

        $this->assertGuest();
    }

    public function test_primary_domain_keeps_its_staff_login(): void
    {
        $this->get('https://sadirfactory.com/login')->assertOk()->assertSee('نظام إدارة الإنتاج والتكاليف الداخلي');
        $this->get('https://sadirfactory.com/dashboard')->assertRedirect('https://sadirfactory.com/login');
    }

    /** @param array<string, mixed> $attributes */
    protected function customerUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', 'customer_portal')->value('id'),
            'customer_id' => Customer::factory()->create()->id,
            'is_active' => true,
        ], $attributes));
    }
}
