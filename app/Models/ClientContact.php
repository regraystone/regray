<?php

namespace App\Models;

use App\Enums\ContactKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property ContactKind $kind
 * @property string $value Normalised (ContactKind::normalise()).
 * @property Carbon $created_at
 */
#[Fillable(['client_id', 'kind', 'value'])]
class ClientContact extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'kind' => ContactKind::class,
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
