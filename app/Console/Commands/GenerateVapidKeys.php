<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Generates the one key pair that lets this application push to installed browsers.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'notifications:vapid';

    protected $description = 'Generate the VAPID key pair for Web Push and print the .env lines';

    public function handle(): int
    {
        $pem = $this->generatePem();

        if (! $pem) {
            $this->error('تعذر توليد المفاتيح: لا امتداد openssl يعمل ولا أداة openssl في المسار.');

            return self::FAILURE;
        }

        $key = openssl_pkey_get_private($pem);
        if (! $key) {
            $this->error('تم توليد المفتاح لكن تعذّرت قراءته.');

            return self::FAILURE;
        }

        $details = openssl_pkey_get_details($key);

        // The browser needs the public key as the raw uncompressed point: 0x04 || X || Y.
        $point = "\x04"
            .str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            .str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $this->newLine();
        $this->info('أضف هذين السطرين إلى ملف .env ثم شغّل: php artisan config:clear');
        $this->newLine();
        $this->line('WEBPUSH_PUBLIC_KEY='.rtrim(strtr(base64_encode($point), '+/', '-_'), '='));
        $this->line('WEBPUSH_PRIVATE_PEM='.base64_encode($pem));
        $this->newLine();
        $this->warn('المفتاح الخاص سرّ: لا تضعه في مستودع الكود، ولا تولّد زوجاً جديداً بعد التشغيل وإلا توقفت الاشتراكات القائمة.');

        return self::SUCCESS;
    }

    /**
     * A P-256 private key in PEM form, from the openssl extension or, where that installation ships
     * no openssl.cnf (typically a Windows development machine), from the openssl command itself.
     */
    private function generatePem(): ?string
    {
        $key = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        if ($key && @openssl_pkey_export($key, $pem)) {
            return $pem;
        }

        while (openssl_error_string()) {
            // Drain the extension's errors; the command line is tried next.
        }

        $result = Process::run('openssl ecparam -genkey -name prime256v1 -noout | openssl pkcs8 -topk8 -nocrypt');

        return $result->successful() && str_contains($result->output(), 'PRIVATE KEY')
            ? trim($result->output())
            : null;
    }
}
