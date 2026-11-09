<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves uploads on the public disk (catalogue covers, model photos) straight from storage, so they
 * show even where public/storage is a copied folder instead of a link to storage/app/public.
 */
class PublicFileController extends Controller
{
    /**
     * Folders on the public disk that may be served.
     */
    public const SERVABLE_FOLDERS = ['fabric-catalogs', 'product_models'];

    public function __invoke(string $path): StreamedResponse
    {
        $folder = explode('/', $path, 2)[0];
        abort_if(str_contains($path, '..') || ! in_array($folder, self::SERVABLE_FOLDERS, true), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }

    /**
     * URL of a public-disk upload, or null when there is none.
     */
    public static function url(?string $path): ?string
    {
        return $path ? route('public-file', ['path' => $path]) : null;
    }
}
