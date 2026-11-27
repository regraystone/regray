<?php

namespace App\Models;

use App\Data\StaffVisibility;
use App\Enums\ConsentType;
use App\Enums\InquiryStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Resources\ShowcaseCardResource;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @property-read Collection<int, ClientAddress> $addresses
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_version
 * @property Carbon|null $marketing_emails_opted_in_at
 * @property string|null $remember_token
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $archived_at
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The column defaults, on the model too, so a user just made in memory
     * (by registration, Google or a factory) already reads as active and not
     * due a password change, before it is ever read back from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

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
     * Whether this user may sign in: active and not archived. The sign-in form,
     * Google sign-in and EnsureAccountActive all ask this.
     */
    public function canSignIn(): bool
    {
        return $this->is_active && $this->archived_at === null;
    }

    /**
     * Their own password is set: the temporary one is gone, and the first
     * sign-in prompt (EnsurePasswordChanged) is over. Every way a user sets a
     * password calls this: the prompt itself, Account & Security, and a reset.
     */
    public function markPasswordChanged(): void
    {
        $this->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
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
     * The photo the user chose for their profile, as a URL, or null for none:
     * a client's, from their profile. Staff have no profile, so none yet.
     * Wherever the user is shown (the header, their messages, an inquiry's
     * details) it stands in for their initials.
     */
    public function avatarUrl(): ?string
    {
        $path = $this->profile?->avatar_path;

        return $path === null || $path === '' ? null : ShowcaseCardResource::resolveThumbnailUrl($path);
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

    /**
     * The client's address book, the default first, then the most recently
     * changed (ClientAddressService).
     *
     * @return HasMany<ClientAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class)->orderByDesc('is_default')->orderByDesc('updated_at')->orderByDesc('id');
    }

    /** @return HasMany<Inquiry, $this> */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'client_id');
    }

    /** @return HasMany<Lead, $this> The client's lead: a client has one at most. */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'client_id');
    }

    /** @return HasOne<Conversation, $this> The client's Regray Team thread. */
    public function supportConversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'support_client_id');
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

    /**
     * Keep the client accounts `$viewer` may find and pick: the one rule for
     * every client search staff make (the Inbox's people search and group
     * creation, the Inquire and Book an appointment pickers). Apply it before
     * the search term and the limit, so neither reaches past it.
     *
     * The admin and the owner (StaffVisibility::$everything) find every
     * client. Anyone else finds a client when any of these holds:
     *  - they hold the client's lead (`leads.marketing_user_id`);
     *  - they read one of the client's inquiries (Inquiry::scopeVisibleTo());
     *  - the client's Regray Team thread is assigned to them;
     *  - the thread is unassigned and they are in the support pool
     *    (`support.claim`), who read it while nobody has it
     *    (ConversationPolicy::view()).
     * A thread another user has claimed gives nothing.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function clientsVisibleTo(Builder $query, User $viewer): void
    {
        $query->whereHas('roles', fn (Builder $role) => $role->where('name', Role::Client->value));

        $visibility = StaffVisibility::for($viewer);

        if ($visibility->everything) {
            return;
        }

        $inSupportPool = $viewer->can(Permission::SupportClaim->value);

        $query->where(function (Builder $query) use ($viewer, $visibility, $inSupportPool): void {
            $query
                ->whereHas('leads', fn (Builder $lead) => $lead->where('marketing_user_id', $viewer->id))
                ->orWhereHas('supportConversation', fn (Builder $thread) => $thread->where(fn (Builder $thread) => $thread
                    ->where('assigned_to', $viewer->id)
                    ->when($inSupportPool, fn (Builder $thread) => $thread->orWhereNull('assigned_to'))));

            if (! $visibility->seesNoInquiry()) {
                $query->orWhereHas('inquiries', fn (Builder $inquiry) => $inquiry->visibleTo($viewer));
            }
        });
    }

    /**
     * How this client reads in a search result: their email when the search
     * is one (it holds "@") and the email holds it, otherwise their name. Run
     * on clients the search already matched inside the viewer's scope
     * (scopeClientsVisibleTo()), so it never reveals an email the viewer
     * could not have found.
     */
    public function searchLabel(?string $search): string
    {
        $search = $search === null ? '' : trim($search);

        return str_contains($search, '@') && str_contains(mb_strtolower($this->email), mb_strtolower($search))
            ? $this->email
            : $this->name;
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

    /** @return HasMany<Schedule, $this> The appointments a sales member is assigned to confirm. */
    public function assignedSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'assigned_to');
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
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'archived_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }
}
