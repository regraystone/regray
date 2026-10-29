<?php

namespace App\Models;

use App\Enums\ClientKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A client the studio works for. `platform` with an account (`user_id`), or
 * `direct`, a walk-in with no account whose contacts staff took down. An
 * account has one client record at most, and two records are never merged.
 *
 * @property int $id
 * @property ClientKind $kind
 * @property int|null $user_id Set exactly when the kind is platform.
 * @property string $full_name
 * @property string|null $company
 * @property string|null $address
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['kind', 'user_id', 'full_name', 'company', 'address', 'created_by'])]
class Client extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ClientKind::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderBy('id');
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'client_record_id');
    }
}
