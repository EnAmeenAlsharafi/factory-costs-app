<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Makes the internal system and the customer portal installable on phones as apps (PWA):
 * one manifest per app, sharing the home-screen icons drawn from the Sadir mark.
 */
class WebAppController extends Controller
{
    public const ICON_SIZES = [192, 512];

    /**
     * Bundled app icon (the Sadir mark). Falls back to the company logo when it is missing.
     */
    public const APP_ICON = 'images/sadir-app-icon.png';

    /**
     * The internal system app (staff).
     */
    public function manifest(): JsonResponse
    {
        $company = CompanySetting::current();

        return $this->manifestResponse([
            'id' => '/dashboard',
            'name' => $company->name_ar,
            'short_name' => $this->shortName($company->name_ar),
            'description' => 'نظام إدارة الإنتاج والمبيعات والمشتريات — '.$company->name_ar,
            'start_url' => '/dashboard?source=app',
            'scope' => '/',
            'theme_color' => $company->primary_color ?: '#1d4ed8',
            'shortcuts' => [
                ['name' => 'لوحة التحكم', 'url' => '/dashboard?source=app'],
                ['name' => 'مهامي', 'url' => '/production/my-tasks?source=app'],
                ['name' => 'مركز تمرير الإنتاج', 'url' => '/production/pipeline?source=app'],
            ],
        ]);
    }

    /**
     * The customer portal app (customers and showrooms): its own name, home page and scope.
     */
    public function portalManifest(): JsonResponse
    {
        $company = CompanySetting::current();
        $brand = $this->lastWord($company->name_ar);

        return $this->manifestResponse([
            'id' => '/portal',
            'name' => 'بوابة عملاء '.$company->name_ar,
            'short_name' => 'بوابة '.$brand,
            'description' => 'اطلب منتجات '.$company->name_ar.' وتابع طلباتك وحسابك.',
            'start_url' => '/portal?source=app',
            'scope' => '/portal',
            'theme_color' => '#142d43',
            'shortcuts' => [
                ['name' => 'طلب جديد', 'url' => '/portal/requests/create?source=app'],
                ['name' => 'طلباتي وعروضي', 'url' => '/portal/requests?source=app'],
                ['name' => 'متابعة التنفيذ', 'url' => '/portal/orders?source=app'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $app
     */
    private function manifestResponse(array $app): JsonResponse
    {
        $version = $this->iconVersion();
        $icon = fn (int $size, string $purpose) => [
            'src' => route('webapp.icon', ['size' => $size, 'purpose' => $purpose, 'v' => $version]),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json($app + [
            'lang' => 'ar',
            'dir' => 'rtl',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'icons' => [$icon(192, 'any'), $icon(512, 'any'), $icon(512, 'maskable')],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Changes whenever the icon source changes, so phones fetch the new icon.
     */
    public function iconVersion(): string
    {
        $source = $this->iconSource();

        return substr(md5(($source ?? 'none').'|'.($source ? filemtime($source) : '').'|'.CompanySetting::current()->updated_at?->timestamp), 0, 10);
    }

    private function iconSource(): ?string
    {
        $bundled = resource_path(self::APP_ICON);

        return is_file($bundled) ? $bundled : CompanySetting::current()->logoFilePath();
    }

    public function icon(int $size, string $purpose = 'any'): BinaryFileResponse
    {
        abort_unless(in_array($size, self::ICON_SIZES, true) && in_array($purpose, ['any', 'maskable'], true), 404);

        $company = CompanySetting::current();
        $logo = $this->iconSource();
        $dir = storage_path('app/app-icons');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.$this->iconVersion()."-{$size}-{$purpose}.png";

        if (! is_file($path)) {
            $this->drawIcon($path, $size, $purpose === 'maskable', $logo, $company->primary_color ?: '#1d4ed8');
        }

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
    }

    /**
     * White square with the logo centred; a maskable icon keeps the logo inside the 80% safe zone.
     * Without a logo, a brand-coloured square with the factory's initial.
     */
    private function drawIcon(string $path, int $size, bool $maskable, ?string $logo, string $primary): void
    {
        $canvas = imagecreatetruecolor($size, $size);
        [$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
        $source = $logo ? @imagecreatefromstring((string) file_get_contents($logo)) : false;

        if ($source) {
            // Trim empty margins (transparent or flat light) and paint the canvas in the logo's own background.
            $corner = imagecolorsforindex($source, imagecolorat($source, 0, 0));
            $isTransparent = $corner['alpha'] > 100;
            $background = $isTransparent ? ['red' => 255, 'green' => 255, 'blue' => 255] : $corner;
            $trimmed = $isTransparent
                ? @imagecropauto($source, IMG_CROP_TRANSPARENT)
                : @imagecropauto($source, IMG_CROP_THRESHOLD, 0.25, imagecolorat($source, 0, 0));
            if ($trimmed) {
                imagedestroy($source);
                $source = $trimmed;
            }
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $background['red'], $background['green'], $background['blue']));
            $box = (int) round($size * ($maskable ? 0.66 : 0.84));
            $ratio = min($box / imagesx($source), $box / imagesy($source));
            $width = (int) round(imagesx($source) * $ratio);
            $height = (int) round(imagesy($source) * $ratio);
            imagecopyresampled($canvas, $source, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
            imagedestroy($source);
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
            $font = resource_path('fonts/Cairo-Bold.ttf');
            $fontSize = $size * ($maskable ? 0.34 : 0.44);
            $box = imagettfbbox($fontSize, 0, $font, 'س');
            $x = (int) (($size - ($box[2] - $box[0])) / 2 - $box[0]);
            $y = (int) (($size - ($box[1] - $box[7])) / 2 - $box[7]);
            imagettftext($canvas, $fontSize, 0, $x, $y, imagecolorallocate($canvas, 255, 255, 255), $font, 'س');
        }

        imagepng($canvas, $path);
        imagedestroy($canvas);
    }

    private function lastWord(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name));

        return end($words) ?: $name;
    }

    /**
     * Home-screen label: the last words of the name that fit in 12 characters (e.g. «مفروشات سدير»).
     */
    private function shortName(string $name): string
    {
        $short = '';
        foreach (array_reverse(preg_split('/\s+/u', trim($name))) as $word) {
            $candidate = trim($word.' '.$short);
            if (mb_strlen($candidate) > 12) {
                break;
            }
            $short = $candidate;
        }

        return $short ?: mb_substr($name, 0, 12);
    }
}
