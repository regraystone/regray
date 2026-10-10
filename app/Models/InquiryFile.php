<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_project_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $sha256
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256'])]
class InquiryFile extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    /** @return BelongsTo<InquiryProject, $this> */
    public function inquiryProject(): BelongsTo
    {
        return $this->belongsTo(InquiryProject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
