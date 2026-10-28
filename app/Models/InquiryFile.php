<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inquiry_id
 * @property DocumentType $document_type
 * @property int|null $revision
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $sha256
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 *
 * Who may upload which document type is App\Policies\InquiryFileUploads.
 */
#[Fillable(['document_type', 'status_history_id', 'revision', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'])]
class InquiryFile extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
        ];
    }

    /** @return BelongsTo<Inquiry, $this> */
    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
