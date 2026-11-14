<?php

namespace App\Models;

use App\Enums\ConsentType;
use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property Carbon|null $marketing_emails_opted_in_at
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

    /**
     * Whether the user wants emails from Regray: message notifications,
     * updates and announcements. Emails about the account itself (verifying
     * the address, resetting the password) are sent regardless.
     *
     * A client is off unless they ticked it, at sign-up or in their settings.
     * Staff never see the sign-up form, so they are on until they turn it off
     * in their settings: one who has never chosen has no email row in
     * `user_consents`.
     */
    public function wantsMarketingEmails(): bool
    {
        if ($this->marketing_emails_opted_in_at !== null) {
            return true;
        }

        return ! $this->isClient()
            && ! $this->consents()->where('type', ConsentType::MarketingEmails)->exists();
    }

    /** Whether a message notification may also be emailed: a verified address that wants emails. */
    public function receivesMessageEmails(): bool
    {
        return $this->email_verified_at !== null && $this->wantsMarketingEmails();
    }

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<UserConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
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

    /**
     * Select `active_inquiry_count`: how many inquiries the user is assigned to
     * (`inquiry_handlers`) that are still open, that is not shown closed or
     * cancelled (InquiryStatusRules::isOpen()). Counted in the database by the
     * display-status rules (Inquiry::scopeWhereDisplayStatus()), so a picker
     * listing staff never loads their inquiries.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withActiveInquiryCount(Builder $query): void
    {
        $query->withCount(['handledInquiries as active_inquiry_count' => fn (Builder $handler) => $handler
            ->whereHas('inquiry', fn (Builder $inquiry) => $inquiry
                ->whereNot(fn (Builder $inquiry) => $inquiry->whereDisplayStatus(InquiryStatus::Closed, InquiryStatus::Cancelled))),
        ]);
    }

    /** @return BelongsToMany<Stone, $this> */
    public function savedStones(): BelongsToMany
    {
        return $this->belongsToMany(Stone::class, 'saved_stones')
            ->withPivot('created_at');
    }

    /** @return BelongsToMany<Furniture, $this> */
    public function savedFurniture(): BelongsToMany
    {
        return $this->belongsToMany(Furniture::class, 'saved_furniture')
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
            'terms_accepted_at' => 'datetime',
            'marketing_emails_opted_in_at' => 'datetime',
        ];
    }
}
