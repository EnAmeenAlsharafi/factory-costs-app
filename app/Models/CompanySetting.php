<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The factory's identity (one row): name, logo, brand colours and legal details for printed documents.
 */
class CompanySetting extends Model
{
    protected $fillable = [
        'name_ar',
        'name_en',
        'logo_path',
        'primary_color',
        'secondary_color',
        'commercial_register',
        'vat_number',
        'phone',
        'email',
        'website',
        'city',
        'address',
        'document_footer',
    ];

    /**
     * The settings row, created with the factory's defaults on first use. Memoised for the request.
     */
    public static function current(): self
    {
        return once(fn () => self::query()->firstOrCreate([], [
            'name_ar' => 'مصنع مفروشات سدير',
            'primary_color' => '#1d4ed8',
            'secondary_color' => '#f5b301',
        ]));
    }

    /**
     * Logo URL served by the app itself; the version busts the browser cache after a new upload.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logoFilePath() ? route('company.logo', ['v' => $this->updated_at?->timestamp]) : null;
    }

    /**
     * Absolute path of the logo file on disk, or null when there is none (used by the PDF renderer).
     */
    public function logoFilePath(): ?string
    {
        return $this->logo_path && Storage::disk('public')->exists($this->logo_path)
            ? Storage::disk('public')->path($this->logo_path)
            : null;
    }
}
