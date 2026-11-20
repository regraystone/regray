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
 * @property string $stored_name
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $sha256
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon|null $deleted_at
 *
 * Who may upload which document type is App\Policies\InquiryFileUploads; who
 * may read one is App\Policies\InquiryFileVisibility.
 */
#[Fillable(['document_type', 'status_history_id', 'revision', 'disk', 'path', 'stored_name', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'])]
class InquiryFile extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    /**
     * The types a browser is trusted to show in a tab, by their label. WebP is
     * not accepted on upload, but one that is ever stored still previews.
     * Anything else (HTML and SVG above all, which can run script) is only
     * ever downloaded.
     *
     * @var array<string, string>
     */
    public const PREVIEWABLE_TYPES = [
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
        ];
    }

    public function isPreviewable(): bool
    {
        return array_key_exists($this->mime_type, self::PREVIEWABLE_TYPES);
    }

    /** "PDF", "JPG" and so on; for any other type, its stored extension. */
    public function formatLabel(): string
    {
        return self::PREVIEWABLE_TYPES[$this->mime_type]
            ?? (strtoupper(pathinfo($this->stored_name, PATHINFO_EXTENSION)) ?: 'FILE');
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
