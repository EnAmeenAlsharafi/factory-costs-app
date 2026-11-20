<?php

namespace App\Notifications;

use App\Models\NotificationRecipient;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use Throwable;

/**
 * Sends Web Push notifications straight from PHP, with no added dependency and nothing to keep
 * running: a signed HTTPS request to the browser vendor's push service, which fits shared hosting.
 *
 * The notification text travels inside the push, encrypted end to end for the receiving browser
 * (RFC 8291, aes128gcm): the push service carries it but cannot read it. Carrying the text matters
 * because the phone must show the notification while the app is closed, when the service worker
 * may no longer have a signed-in session to ask the application with.
 */
class WebPushService
{
    /** P-256 public keys as DER SubjectPublicKeyInfo: this prefix, then the 65-byte uncompressed point. */
    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

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
     * What a phone shows for this person: their newest unread notification, and how many more wait.
     *
     * @return array{count: int, title?: string, body?: ?string, url?: string, tag?: string}
     */
    public function messageFor(User $user): array
    {
        $unread = NotificationRecipient::forUser($user)->unread()->with('notification')->latest('id')->get();
        $newest = $unread->first()?->notification;

        if (! $newest) {
            return ['count' => 0];
        }

        return [
            'count' => $unread->count(),
            'title' => $newest->title_ar,
            'body' => $unread->count() > 1
                ? trim($newest->body_ar."\n".'ولديك '.($unread->count() - 1).' إشعاراً آخر بانتظارك.')
                : $newest->body_ar,
            'url' => $newest->url ?: ($user->isCustomerAccount() ? route('portal.notifications.index') : route('notifications.index')),
            'tag' => 'sadir-notifications',
        ];
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

        $message = $this->messageFor($user);
        if ($message['count'] === 0) {
            return 0;
        }

        $payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sent = 0;

        foreach (PushSubscription::where('user_id', $user->id)->get() as $subscription) {
            if ($this->send($subscription, $payload)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * A subscription the push service has rejected for good is dropped: the browser was uninstalled,
     * its data cleared, or permission withdrawn.
     */
    private function send(PushSubscription $subscription, string $payload): bool
    {
        try {
            $body = $this->encrypt($payload, $subscription->p256dh, $subscription->auth);

            $headers = [
                'Authorization' => 'vapid t='.$this->token($subscription->audience()).', k='.$this->publicKey(),
                'TTL' => '86400',
                // High: the phone is woken from battery saving at once; this is a person being told something.
                'Urgency' => 'high',
            ];

            $request = Http::withHeaders($headers)->timeout(8);
            $response = $body === null
                // The browser's keys could not be used: an empty push still wakes the worker, which then asks the app.
                ? $request->withHeaders(['Content-Length' => '0'])->send('POST', $subscription->endpoint)
                : $request->withHeaders(['Content-Encoding' => 'aes128gcm'])
                    ->withBody($body, 'application/octet-stream')
                    ->post($subscription->endpoint);

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
     * Encrypt a payload for one browser (RFC 8291 with the aes128gcm content coding of RFC 8188).
     *
     * Returns null when the browser's keys are unusable or this server cannot make a P-256 key, so
     * the caller can still send an empty wake-up push.
     *
     * @param  ?OpenSSLAsymmetricKey  $senderKey  a fresh P-256 key per message; passed in by tests only
     */
    public function encrypt(string $payload, string $browserPublicKey, string $browserAuthSecret, ?OpenSSLAsymmetricKey $senderKey = null): ?string
    {
        $uaPublic = $this->base64UrlDecode($browserPublicKey);
        $authSecret = $this->base64UrlDecode($browserAuthSecret);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04" || strlen($authSecret) !== 16) {
            return null;
        }

        $senderKey ??= @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]) ?: null;
        $uaKey = openssl_pkey_get_public($this->publicKeyPem($uaPublic));

        if (! $senderKey || ! $uaKey) {
            while (openssl_error_string());

            return null;
        }

        $ec = openssl_pkey_get_details($senderKey)['ec'];
        $asPublic = "\x04".str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT).str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);

        $sharedSecret = openssl_pkey_derive($uaKey, $senderKey, 32);
        if ($sharedSecret === false) {
            return null;
        }

        $ikm = hash_hkdf('sha256', $sharedSecret, 32, "WebPush: info\x00".$uaPublic.$asPublic, $authSecret);
        $salt = random_bytes(16);
        $contentKey = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt);

        // One record: the payload, then the 0x02 delimiter that marks the last record.
        $ciphertext = openssl_encrypt($payload."\x02", 'aes-128-gcm', $contentKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            return null;
        }

        return $salt.pack('N', 4096).chr(strlen($asPublic)).$asPublic.$ciphertext.$tag;
    }

    /**
     * A raw uncompressed P-256 point as a PEM public key OpenSSL can load.
     */
    public function publicKeyPem(string $uncompressedPoint): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX).$uncompressedPoint), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
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

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }
}
