<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Sentry\State\Scope;

use function Sentry\configureScope;

/**
 * Tells Sentry who hit an error: the user's id and role, and nothing else.
 *
 * `send_default_pii` is off (config/sentry.php), so the SDK attaches no user
 * of its own: no email, name or IP. Authenticated fires whenever the guard
 * settles on a user, at login and on every request that resumes a session.
 * Does nothing while no DSN is set.
 */
class TagSentryUser
{
    public function handle(Authenticated $event): void
    {
        if (! config('sentry.dsn') || ! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        configureScope(function (Scope $scope) use ($user): void {
            $scope->setUser(['id' => (string) $user->getKey()]);
            $scope->setTag('role', $user->primaryRole()->value ?? 'none');
        });
    }
}
