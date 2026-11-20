<?php

namespace App\Models;

use Database\Factories\PersonalNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private note: no due date, no status, seen by its author only.
 */
class PersonalNote extends Model
{
    /** @use HasFactory<PersonalNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
