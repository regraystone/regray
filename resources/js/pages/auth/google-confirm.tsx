import { Form, Head, Link } from '@inertiajs/react';
import GoogleController from '@/actions/App/Http/Controllers/Auth/GoogleController';
import { Button } from '@/components/ui/button';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import { request } from '@/routes/password';

/**
 * Connect your Google account: the Google email is already an account's, and
 * that account's password is asked for once before the two are connected.
 * Owning the email at Google is not enough on its own: somebody may have
 * registered with an address that is not theirs.
 */
export default function GoogleConfirm({ email }: { email: string }) {
    return (
        <>
            <Head title="Connect your Google account" />

            <p className="mb-5 text-sm text-secondary-foreground">
                You already have an account with{' '}
                <span className="font-semibold text-foreground">{email}</span>.
                Enter its password once to connect Google. After that you can
                sign in either way.
            </p>

            <Form
                {...GoogleController.confirm.form()}
                resetOnError={['password']}
                className="space-y-5 max-md:space-y-(--auth-field-gap)"
            >
                {({ processing, errors }) => (
                    <>
                        <PasswordField
                            id="password"
                            name="password"
                            type="password"
                            label="Password"
                            placeholder="Password"
                            autoComplete="current-password"
                            autoFocus
                            tabIndex={1}
                            error={errors.password}
                        />

                        <div className="flex justify-end">
                            {/* Whoever owns the email can reset it from here. */}
                            <Link
                                href={request()}
                                className="text-[0.8rem] font-semibold text-secondary-foreground transition-opacity hover:opacity-85"
                                tabIndex={4}
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            tabIndex={2}
                            disabled={processing}
                            data-test="google-confirm-button"
                        >
                            {processing && <Spinner />}
                            Connect and sign in
                        </Button>
                    </>
                )}
            </Form>

            <Button
                asChild
                variant="outline"
                className="mt-3 w-full"
                tabIndex={3}
            >
                <Link
                    href={GoogleController.cancelConfirm.url()}
                    method="delete"
                    as="button"
                    data-test="google-confirm-cancel"
                >
                    Cancel
                </Link>
            </Button>
        </>
    );
}

GoogleConfirm.layout = [
    AuthSheetLayout,
    {
        title: 'Connect your Google account',
    },
];
