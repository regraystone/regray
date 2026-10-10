<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['path', 'alt_text', 'sort_order'])]
class ProjectImage extends Model
{
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
