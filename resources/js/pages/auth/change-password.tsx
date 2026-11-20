import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    AuthFootnote,
    authLink,
    authPrimaryButton,
} from '@/components/auth/auth-ui';
import LogoutDialog from '@/components/logout-dialog';
import { Button } from '@/components/ui/button';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import { update } from '@/routes/password/change';

/**
 * Set your password: an account made on the Accounts page starts on a
 * temporary password, and its holder lands here on their first sign-in until
 * they choose their own (EnsurePasswordChanged).
 */
export default function ChangePassword({
    passwordRules,
}: {
    passwordRules: string;
}) {
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    return (
        <>
            <Head title="Set your password" />

            <Form
                {...update.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-7"
            >
                {({ processing, errors }) => (
                    <>
                        <PasswordField
                            id="password"
                            name="password"
                            variant="line"
                            label="New password"
                            autoComplete="new-password"
                            autoFocus
                            passwordrules={passwordRules}
                            error={errors.password}
                        />

                        <PasswordField
                            id="password_confirmation"
                            name="password_confirmation"
                            variant="line"
                            label="Confirm password"
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                            error={errors.password_confirmation}
                        />

                        <Button
                            type="submit"
                            className={authPrimaryButton}
                            disabled={processing}
                            data-test="change-password-button"
                        >
                            {processing && <Spinner />}
                            Set password
                        </Button>
                    </>
                )}
            </Form>

            <AuthFootnote>
                Not now?{' '}
                <button
                    type="button"
                    onClick={() => setIsLoggingOut(true)}
                    className={`cursor-pointer ${authLink}`}
                >
                    Log out
                </button>
            </AuthFootnote>

            <LogoutDialog open={isLoggingOut} onOpenChange={setIsLoggingOut} />
        </>
    );
}

ChangePassword.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Account',
        title: 'Set your password',
        description:
            'Your account was made with a temporary password. Choose your own to continue.',
    },
];
