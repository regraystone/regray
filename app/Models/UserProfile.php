<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property string $phone
 * @property string|null $company
 * @property string $address
 * @property string|null $avatar_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['phone', 'company', 'address', 'avatar_path'])]
class UserProfile extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'user_id';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
