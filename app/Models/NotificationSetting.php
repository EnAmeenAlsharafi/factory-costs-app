<?php

namespace App\Models;

use App\Notifications\NotificationType;
use Illuminate\Database\Eloquent\Model;

/**
 * Switches one audience of one notification type off. A type/audience pair with no row is on:
 * the catalogue's defaults apply until somebody changes them.
 */
class NotificationSetting extends Model
{
    protected $fillable = [
        'type',
        'audience_key',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Audiences switched off, as "TYPE|audience" keys.
     *
     * @return array<string, true>
     */
    public static function disabledMap(): array
    {
        return static::query()->where('is_enabled', false)->get()
            ->mapWithKeys(fn (self $setting) => [$setting->type->value.'|'.$setting->audience_key => true])
            ->all();
    }
}
