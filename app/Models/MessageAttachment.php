<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon $created_at
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size_bytes'])]
class MessageAttachment extends Model
{
    public const UPDATED_AT = null;

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
