<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One photo of a project's gallery, in `sort_order` from 0. An uploaded one
 * keeps its own name, type and size (null for a committed one).
 *
 * @property int $id
 * @property int $project_id
 * @property string $path
 * @property int $sort_order
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['path', 'sort_order', 'original_name', 'mime_type', 'size_bytes'])]
class ProjectImage extends Model
{
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * What the photo is called: its uploader's file name without the
     * extension ("kitchen-island" for kitchen-island.jpg), the alt text the
     * gallery shows. Null for a photo that was committed rather than
     * uploaded, which the gallery numbers instead.
     */
    public function label(): ?string
    {
        $name = $this->original_name === null ? '' : trim(pathinfo($this->original_name, PATHINFO_FILENAME));

        return $name === '' ? null : $name;
    }
}
