<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RedirectController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Set your password: an account made on the Accounts page starts on the
 * temporary password (config/accounts.php), and EnsurePasswordChanged keeps
 * its holder here until they choose their own, which may not be that one.
 */
class PasswordChangeController extends Controller
{
    use PasswordValidationRules;

    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Already their own: nothing to set here.
        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/change-password', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate(['password' => $this->passwordRules()]);

        // The temporary password is shared with whoever made the account, so it
        // is never one's own. Asked only once the password rules pass.
        if (hash_equals(config()->string('accounts.default_password'), $request->string('password')->toString())) {
            throw ValidationException::withMessages(['password' => __('Choose a password different from the temporary one.')]);
        }

        $user->forceFill(['password' => $request->string('password')->toString()]);
        $user->markPasswordChanged();
        $user->save();

        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your password is set.')]);

        return (new RedirectController)->dashboard($request);
    }
}
