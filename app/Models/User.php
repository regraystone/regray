<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * The user's first Spatie role, used for landing-page redirects.
     *
     * A user normally has exactly one role, but nothing here prevents more
     * from being assigned later, so this is only the first of the list. A user
     * whose first role is not one the enum knows about resolves to null, which
     * the redirect treats the same as having no role at all.
     */
    public function primaryRole(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name === null ? null : Role::tryFrom($name);
    }

    /**
     * Whether this user is a client of the studio rather than staff.
     *
     * Clients talk in their own Regray Team thread and may be added to group
     * chats; everybody else is staff.
     */
    public function isClient(): bool
    {
        return $this->hasRole(Role::Client->value);
    }

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'client_id');
    }

    /** @return HasMany<InquiryHandler, $this> */
    public function handledInquiries(): HasMany
    {
        return $this->hasMany(InquiryHandler::class);
    }

    /** @return BelongsToMany<Stone, $this> */
    public function savedStones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'saved_stones')
            ->withPivot('created_at');
    }

    /** @return BelongsToMany<Conversation, $this> */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members');
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'client_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
