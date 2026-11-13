<?php

namespace App\Services;

use App\Enums\SocialProvider;
use App\Exceptions\SocialSignInException;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Signing in and signing up with an outside account, such as Google.
 *
 * An outside account is known by the provider's own id, which never changes.
 * The first time one is seen, an account registered with the same email is
 * recognised, but only for an address the provider has verified, and only
 * connected once the visitor gives that account's password: somebody may have
 * registered with an email they do not own, and owning it at the provider
 * must not hand them, or take from them, that account.
 */
class SocialSignInService
{
    public function __construct(private readonly ClientRegistrationService $registration) {}

    /** The account this outside account is already connected to, if any. */
    public function connectedAccount(SocialProvider $provider, SocialiteUser $outside): ?User
    {
        return $this->linkedAccount($provider, (string) $outside->getId())?->user;
    }

    /**
     * The account already registered with this outside account's email, if
     * any. It is never signed in to from here: the visitor must give that
     * account's password before the two are connected (confirmLink()), so
     * owning the email at the provider is not enough to take an account that
     * somebody else registered with it.
     *
     * @throws SocialSignInException
     */
    public function accountWithEmail(SocialProvider $provider, SocialiteUser $outside): ?User
    {
        $this->assertVerifiedEmail($provider, $outside);

        return $this->findByEmail((string) $outside->getEmail());
    }

    /**
     * Connect the outside account once its visitor has given the account's
     * password. The email counts as verified from now on: the provider has
     * verified it, and the password says it is the account's owner.
     *
     * @throws SocialSignInException
     */
    public function confirmLink(User $user, SocialProvider $provider, string $providerUserId, ?string $email): void
    {
        DB::transaction(function () use ($user, $provider, $providerUserId, $email): void {
            $this->link($user, $provider, $providerUserId, $email);

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => Date::now()])->save();
            }
        });
    }

    /**
     * Connect an outside account to a user: from their settings while signed
     * in, or after they gave their password (confirmLink()).
     *
     * @throws SocialSignInException
     */
    public function link(User $user, SocialProvider $provider, string $providerUserId, ?string $email): SocialAccount
    {
        $linked = $this->linkedAccount($provider, $providerUserId);

        if ($linked !== null && $linked->user_id !== $user->id) {
            throw new SocialSignInException(__('This :provider account is already connected to another Regray account.', ['provider' => $provider->label()]));
        }

        if ($linked !== null) {
            return $linked;
        }

        if ($user->socialAccounts()->where('provider', $provider)->exists()) {
            throw new SocialSignInException(__('Your account is already connected to a different :provider account. Disconnect it first from Account & Security.', ['provider' => $provider->label()]));
        }

        return $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email,
        ]);
    }

    /**
     * Make the client account for a visitor signing up with an outside account,
     * once they have agreed to the terms. Their email is verified: the provider
     * has verified it. They have no password of their own; "Forgot password"
     * sets one if they ever want to sign in without the provider.
     *
     * @param  array{provider_user_id: string, email: string, name: string}  $pending
     */
    public function register(SocialProvider $provider, array $pending, string $name, bool $marketingEmails, Request $request): User
    {
        return DB::transaction(function () use ($provider, $pending, $name, $marketingEmails, $request): User {
            $user = $this->registration->register(
                $name,
                $pending['email'],
                Str::password(64),
                $marketingEmails,
                $request,
                emailVerified: true,
            );

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $pending['provider_user_id'],
                'email' => $pending['email'],
            ]);

            return $user;
        });
    }

    /** An account with this email, written in any case. */
    public function findByEmail(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    /** @throws SocialSignInException */
    public function assertVerifiedEmail(SocialProvider $provider, SocialiteUser $outside): void
    {
        $raw = $outside instanceof AbstractUser ? $outside->getRaw() : [];
        $verified = filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (blank($outside->getEmail()) || ! $verified) {
            throw new SocialSignInException(__(':provider has not verified the email address on this account, so it cannot be used to sign in.', ['provider' => $provider->label()]));
        }
    }

    private function linkedAccount(SocialProvider $provider, string $providerUserId): ?SocialAccount
    {
        return SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }
}
