<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property bool $is_advisory
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'is_advisory', 'is_active', 'sort_order'])]
class Finish extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_advisory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return Builder<Finish> */
    public function active(): Builder
    {
        return $this->where('is_active', true);
    }
}
