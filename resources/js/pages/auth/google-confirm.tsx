import { Form, Head, Link } from '@inertiajs/react';
import GoogleController from '@/actions/App/Http/Controllers/Auth/GoogleController';
import {
    authOutlineButton,
    authPrimaryButton,
    authQuietLink,
} from '@/components/auth/auth-ui';
import { Button } from '@/components/ui/button';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import { request } from '@/routes/password';

/**
 * Connect your Google account: the Google email is already an account's, and
 * that account's password is asked for once before the two are connected.
 * Owning the email at Google is not enough on its own: somebody may have
 * registered with an address that is not theirs.
 */
export default function GoogleConfirm({ email }: { email: string }) {
    return (
        <div className="flex flex-col gap-7">
            <Head title="Connect your Google account" />

            <div className="flex flex-col gap-1.5 border-y border-line py-5">
                <span className="text-[0.9375rem] font-semibold text-ink-soft">
                    Your account
                </span>
                <span className="text-lg break-all">{email}</span>
            </div>

            <Form
                {...GoogleController.confirm.form()}
                resetOnError={['password']}
                className="flex flex-col gap-7"
            >
                {({ processing, errors }) => (
                    <>
                        <PasswordField
                            id="password"
                            name="password"
                            type="password"
                            variant="line"
                            label="Password"
                            autoComplete="current-password"
                            autoFocus
                            tabIndex={1}
                            hint={
                                // Whoever owns the email can reset it from here.
                                <Link
                                    href={request()}
                                    className={authQuietLink}
                                    tabIndex={4}
                                >
                                    Forgot password?
                                </Link>
                            }
                            error={errors.password}
                        />

                        <Button
                            type="submit"
                            className={authPrimaryButton}
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
                className={`-mt-3.5 ${authOutlineButton}`}
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
        </div>
    );
}

GoogleConfirm.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Account',
        title: 'Connect your Google account',
        description:
            'You already have an account with this email. Enter its password once to connect Google; after that you can sign in either way.',
    },
];
