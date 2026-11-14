<?php

namespace App\Services;

use App\Enums\ConsentSource;
use App\Enums\ConsentType;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The terms a user agreed to and whether they want emails from Regray
 * (User::wantsMarketingEmails(), which is on for staff until they say no).
 *
 * Each change writes the user's columns, which say where they stand now, and
 * a `user_consents` row, which is the record of when and from where it was
 * given. Both in one transaction, so the two never disagree.
 */
class ConsentService
{
    /**
     * What a new account agreed to on the sign-up form: the current terms,
     * which the form will not submit without, and promotional emails if they
     * ticked the box. A box left empty is recorded too, as not granted.
     */
    public function recordRegistration(User $user, bool $marketingEmails, Request $request): void
    {
        DB::transaction(function () use ($user, $marketingEmails, $request): void {
            $now = Date::now();
            $version = (string) config('legal.terms_version');

            $user->forceFill([
                'terms_accepted_at' => $now,
                'terms_version' => $version,
                'marketing_emails_opted_in_at' => $marketingEmails ? $now : null,
            ])->save();

            $this->record($user, ConsentType::Terms, true, ConsentSource::Registration, $request, $version);
            $this->record($user, ConsentType::MarketingEmails, $marketingEmails, ConsentSource::Registration, $request);
        });
    }

    /**
     * Turn promotional emails on or off from the user's settings. Saving the
     * choice they already have changes nothing and records nothing.
     */
    public function setMarketingEmails(User $user, bool $wanted, Request $request): void
    {
        if ($user->wantsMarketingEmails() === $wanted) {
            return;
        }

        DB::transaction(function () use ($user, $wanted, $request): void {
            $user->forceFill(['marketing_emails_opted_in_at' => $wanted ? Date::now() : null])->save();

            $this->record($user, ConsentType::MarketingEmails, $wanted, ConsentSource::Settings, $request);
        });
    }

    private function record(User $user, ConsentType $type, bool $granted, ConsentSource $source, Request $request, ?string $version = null): void
    {
        UserConsent::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'granted' => $granted,
            'version' => $version,
            'source' => $source,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent() === null ? null : Str::limit($request->userAgent(), 255, ''),
        ]);
    }
}
