<?php

namespace App\Models;

use App\Enums\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Marketing's record of one client, created when a marketing user is given the
 * client's Regray Team thread. Its status is marketing's: idle until the client
 * is won or lost, and final after. The client's inquiries point to it.
 *
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property int $marketing_user_id
 * @property LeadStatus $status
 * @property Carbon $status_changed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['reference', 'client_id', 'marketing_user_id', 'status', 'status_changed_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function marketingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    /** @return HasMany<LeadStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }
}
