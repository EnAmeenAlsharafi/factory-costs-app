<?php

namespace App\Notifications;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends Web Push notifications straight from PHP, with no added dependency and nothing to keep
 * running: a signed HTTPS request to the browser vendor's push service, which fits shared hosting.
 *
 * The push carries no payload. It wakes the service worker, which then asks this application what
 * to show — so notification text never passes through Google's or Apple's servers, and the heavy
 * payload encryption the Web Push standard would otherwise require is not needed at all.
 */
class WebPushService
{
    /**
     * Whether VAPID keys are configured. Without them the application simply never pushes.
     */
    public function isConfigured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_pem'));
    }

    public function publicKey(): ?string
    {
        return config('webpush.public_key') ?: null;
    }

    /**
     * Wake every browser this person installed the app on.
     *
     * @return int how many were reached
     */
    public function notify(User $user): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $sent = 0;
        foreach (PushSubscription::where('user_id', $user->id)->get() as $subscription) {
            if ($this->send($subscription)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * A subscription the push service has rejected for good is dropped: the browser was uninstalled,
     * its data cleared, or permission withdrawn.
     */
    private function send(PushSubscription $subscription): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'vapid t='.$this->token($subscription->audience()).', k='.$this->publicKey(),
                'TTL' => '86400',
                'Content-Length' => '0',
                'Urgency' => 'normal',
            ])->timeout(8)->send('POST', $subscription->endpoint);

            if (in_array($response->status(), [404, 410], true)) {
                $subscription->delete();

                return false;
            }

            if ($response->successful()) {
                $subscription->forceFill(['last_used_at' => now()])->save();

                return true;
            }

            report(new \RuntimeException("Web push rejected with {$response->status()}: ".mb_substr($response->body(), 0, 200)));

            return false;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * The VAPID token: an ES256 JWT that proves to the push service who is sending.
     */
    private function token(string $audience): string
    {
        $header = $this->base64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64Url(json_encode([
            'aud' => $audience,
            'exp' => now()->addHours(12)->timestamp,
            'sub' => config('webpush.subject'),
        ]));

        $privateKey = openssl_pkey_get_private(base64_decode((string) config('webpush.private_pem')));
        if (! $privateKey) {
            throw new \RuntimeException('مفتاح الدفع الخاص غير صالح. أعد توليد مفاتيح VAPID.');
        }

        openssl_sign("{$header}.{$payload}", $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        return "{$header}.{$payload}.".$this->base64Url($this->rawSignature($derSignature));
    }

    /**
     * OpenSSL signs with a DER SEQUENCE of two integers; JWT wants the two 32-byte halves raw.
     */
    private function rawSignature(string $der): string
    {
        // 30 <len> 02 <rLen> <r> 02 <sLen> <s>
        $rLength = ord($der[3]);
        $r = substr($der, 4, $rLength);

        $sLength = ord($der[5 + $rLength]);
        $s = substr($der, 6 + $rLength, $sLength);

        return $this->pad($r).$this->pad($s);
    }

    /**
     * A DER integer may carry a leading zero (sign bit) or be short; P-256 wants exactly 32 bytes.
     */
    private function pad(string $value): string
    {
        $value = ltrim($value, "\x00");

        return str_pad($value, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
