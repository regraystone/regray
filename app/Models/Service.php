<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a client can ask for: an entry of the list "What do you need?" on the
 * Inquire page.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property bool $needs_furniture
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['slug', 'name', 'needs_furniture', 'is_active', 'sort_order'])]
class Service extends Model
{
    protected function casts(): array
    {
        return [
            'needs_furniture' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<InquiryWorkItem, $this> */
    public function workItems(): HasMany
    {
        return $this->hasMany(InquiryWorkItem::class);
    }
}
