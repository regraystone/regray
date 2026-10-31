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
 * Marketing's record of one client, kept for good: created when a marketing
 * user is given the client's Regray Team thread, held by whoever holds the
 * thread, and pointed to by every inquiry of the client. It has no status of
 * its own: marketing decides each inquiry, and the lead shows the tally.
 *
 * @property int $id
 * @property string $reference
 * @property int $client_id
 * @property int $marketing_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['reference', 'client_id', 'marketing_user_id'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

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

    /**
     * How many of its inquiries marketing has won and lost, and how many still
     * wait for a decision (idle). Reads the loaded inquiries.
     *
     * @return array{won: int, lost: int, idle: int}
     */
    public function tally(): array
    {
        $decisions = $this->inquiries->map(fn (Inquiry $inquiry): ?LeadStatus => $inquiry->marketing_status);

        return [
            LeadStatus::Won->value => $decisions->filter(fn (?LeadStatus $status): bool => $status === LeadStatus::Won)->count(),
            LeadStatus::Lost->value => $decisions->filter(fn (?LeadStatus $status): bool => $status === LeadStatus::Lost)->count(),
            LeadStatus::Idle->value => $decisions->filter(fn (?LeadStatus $status): bool => $status === null)->count(),
        ];
    }

    /** @return HasMany<LeadStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }
}
