<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The first choice of "What do you need?" on the Inquire page, which decides the
 * services the second one offers. Managed on the admin's Services page; an
 * archived one (`archived_at`) is also inactive, and leaves the page.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['slug', 'name', 'sort_order', 'is_active'])]
class ServiceType extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The service types still on the admin's Services page: hidden or not, but not
     * archived.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function notArchived(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('archived_at'));
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
