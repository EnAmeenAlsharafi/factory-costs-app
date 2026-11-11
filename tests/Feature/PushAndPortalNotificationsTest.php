<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPortalRequest;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use App\Notifications\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PushAndPortalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $customerAccount;

    private User $otherCustomerAccount;

    private User $manager;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->customer = Customer::first();
        $this->customerAccount = User::factory()->create([
            'role_id' => Role::where('name', 'customer_portal')->value('id'),
            'customer_id' => $this->customer->id,
            'is_active' => true,
        ]);
        $this->otherCustomerAccount = User::factory()->create([
            'role_id' => Role::where('name', 'customer_portal')->value('id'),
            'customer_id' => Customer::where('id', '!=', $this->customer->id)->value('id'),
            'is_active' => true,
        ]);
        $this->manager = User::factory()->create([
            'role_id' => Role::where('name', 'production_manager')->value('id'),
            'is_active' => true,
        ]);
    }

    private function tellTheCustomer(): ?Notification
    {
        return app(NotificationService::class)->raise(NotificationType::PortalOfferReady, [
            'title' => 'وصلك عرض سعر جديد',
            'body' => 'راجع العرض وأكّده لنبدأ التنفيذ.',
            'customer_id' => $this->customer->id,
            'actor' => $this->manager,
        ]);
    }

    public function test_a_portal_notification_reaches_that_customer_only(): void
    {
        $this->tellTheCustomer();

        $recipients = NotificationRecipient::pluck('user_id')->all();

        $this->assertSame([$this->customerAccount->id], $recipients);
        $this->assertNotContains($this->otherCustomerAccount->id, $recipients);
        $this->assertNotContains($this->manager->id, $recipients);
    }

    public function test_a_customer_account_is_never_an_audience_of_a_factory_notification(): void
    {
        app(NotificationService::class)->raise(NotificationType::StageOverdue, [
            'title' => 'مرحلة متأخرة',
            'audiences' => ['permission:production.release', 'role:admin', 'department'],
            'department_id' => 1,
        ]);

        $this->assertSame(
            0,
            NotificationRecipient::where('user_id', $this->customerAccount->id)->count(),
            'A customer must never receive anything addressed to the factory.',
        );
    }

    public function test_a_customer_sees_their_notifications_and_staff_cannot_open_the_portal_list(): void
    {
        $this->tellTheCustomer();

        $this->actingAs($this->customerAccount)->get(route('portal.notifications.index'))
            ->assertOk()
            ->assertSee('وصلك عرض سعر جديد');

        // A staff account on a portal page is sent to the portal's own sign-in, never shown the list.
        $this->actingAs($this->manager)->get(route('portal.notifications.index'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_a_customer_cannot_read_another_customers_notification(): void
    {
        $this->tellTheCustomer();
        $recipient = NotificationRecipient::forUser($this->customerAccount)->firstOrFail();

        $this->actingAs($this->otherCustomerAccount)
            ->post(route('portal.notifications.read', $recipient))
            ->assertForbidden();

        $this->assertNull($recipient->fresh()->read_at);
    }

    public function test_an_offer_sent_to_the_portal_tells_the_customer(): void
    {
        $submission = CustomerPortalRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->customerAccount->id,
        ]);

        app(NotificationService::class)->raise(NotificationType::PortalOfferReady, [
            'title' => 'وصلك عرض سعر جديد',
            'customer_id' => $submission->customer_id,
            'subject' => $submission,
            'actor' => $this->manager,
        ]);

        $notification = Notification::ofType(NotificationType::PortalOfferReady)->firstOrFail();
        $this->assertTrue($notification->type->isForCustomer());
        $this->assertSame('success', $notification->severity);
    }

    public function test_a_browser_can_subscribe_and_unsubscribe_for_push(): void
    {
        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc-123',
            'keys' => ['p256dh' => 'public-key-value', 'auth' => 'auth-value'],
        ];

        $this->actingAs($this->manager)->postJson(route('push.subscribe'), $subscription)
            ->assertOk()->assertJsonPath('status', 'subscribed');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->manager->id,
            'endpoint' => $subscription['endpoint'],
        ]);

        // Subscribing the same browser again must not create a second row.
        $this->actingAs($this->manager)->postJson(route('push.subscribe'), $subscription)->assertOk();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($this->manager)->postJson(route('push.unsubscribe'), ['endpoint' => $subscription['endpoint']])
            ->assertOk();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_customer_account_may_also_register_a_browser(): void
    {
        $this->actingAs($this->customerAccount)->postJson(route('push.subscribe'), [
            'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/xyz',
            'keys' => ['p256dh' => 'p', 'auth' => 'a'],
        ])->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $this->customerAccount->id]);
    }

    public function test_the_woken_service_worker_is_told_what_is_waiting(): void
    {
        $this->tellTheCustomer();

        $this->actingAs($this->customerAccount)->getJson(route('push.latest'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('title', 'وصلك عرض سعر جديد');
    }

    public function test_nothing_unread_means_nothing_to_show(): void
    {
        $this->actingAs($this->manager)->getJson(route('push.latest'))
            ->assertOk()->assertJsonPath('count', 0);
    }

    public function test_the_sender_pushes_once_and_drops_a_subscription_the_service_rejects(): void
    {
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_pem' => base64_encode($this->privatePem())]);
        Http::fake(['https://push.example.test/*' => Http::response('', 201)]);

        PushSubscription::create([
            'user_id' => $this->customerAccount->id,
            'endpoint' => 'https://push.example.test/sub-1',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        $this->tellTheCustomer();

        $this->artisan('notifications:push')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull(NotificationRecipient::forUser($this->customerAccount)->first()->pushed_at);

        // A second run has nothing left to push.
        $this->artisan('notifications:push')->assertSuccessful();
        Http::assertSentCount(1);
    }

    public function test_a_subscription_the_push_service_has_forgotten_is_deleted(): void
    {
        config(['webpush.public_key' => 'test-public-key', 'webpush.private_pem' => base64_encode($this->privatePem())]);
        Http::fake(['https://push.example.test/*' => Http::response('', 410)]);

        PushSubscription::create([
            'user_id' => $this->customerAccount->id,
            'endpoint' => 'https://push.example.test/gone',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        $this->tellTheCustomer();

        $this->artisan('notifications:push')->assertSuccessful();

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_nothing_is_pushed_while_vapid_keys_are_missing(): void
    {
        config(['webpush.public_key' => null, 'webpush.private_pem' => null]);
        Http::fake();
        PushSubscription::create([
            'user_id' => $this->customerAccount->id,
            'endpoint' => 'https://push.example.test/sub',
            'p256dh' => 'p',
            'auth' => 'a',
        ]);
        $this->tellTheCustomer();

        $this->artisan('notifications:push')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFalse(app(WebPushService::class)->isConfigured());
    }

    public function test_the_vapid_token_is_a_valid_es256_signature(): void
    {
        $pem = $this->privatePem();
        config(['webpush.public_key' => 'k', 'webpush.private_pem' => base64_encode($pem), 'webpush.subject' => 'mailto:a@b.c']);

        $token = (new \ReflectionMethod(WebPushService::class, 'token'))
            ->invoke(app(WebPushService::class), 'https://push.example.test');

        [$header, $payload, $signature] = explode('.', $token);
        $raw = base64_decode(strtr($signature, '-_', '+/').str_repeat('=', (4 - strlen($signature) % 4) % 4));

        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(base64_decode(strtr($header, '-_', '+/')), true));
        $this->assertSame('https://push.example.test', json_decode(base64_decode(strtr($payload, '-_', '+/')), true)['aud']);
        $this->assertSame(64, strlen($raw), 'ES256 wants the two 32-byte halves, not the DER OpenSSL returns.');

        $public = openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];
        $this->assertSame(1, openssl_verify("{$header}.{$payload}", $this->toDer($raw), $public, OPENSSL_ALGO_SHA256));
    }

    /**
     * A throwaway P-256 key for the signing tests, generated by the openssl command so the test
     * does not depend on the extension's key generation (which needs an openssl.cnf).
     */
    private function privatePem(): string
    {
        static $pem = null;

        if ($pem === null) {
            $key = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            if ($key && @openssl_pkey_export($key, $exported)) {
                $pem = $exported;
            } else {
                while (openssl_error_string());
                $result = Process::run('openssl ecparam -genkey -name prime256v1 -noout | openssl pkcs8 -topk8 -nocrypt');
                if (! $result->successful() || ! str_contains($result->output(), 'PRIVATE KEY')) {
                    $this->markTestSkipped('No way to generate a P-256 key on this machine.');
                }
                $pem = trim($result->output());
            }
        }

        return $pem;
    }

    /**
     * Rebuild the DER signature OpenSSL verifies from the raw halves the JWT carries.
     */
    private function toDer(string $raw): string
    {
        $integer = function (string $value): string {
            $value = ltrim($value, "\x00");
            if (ord($value[0]) > 0x7F) {
                $value = "\x00".$value;
            }

            return "\x02".chr(strlen($value)).$value;
        };

        $body = $integer(substr($raw, 0, 32)).$integer(substr($raw, 32, 32));

        return "\x30".chr(strlen($body)).$body;
    }
}
