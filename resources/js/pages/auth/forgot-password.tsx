import { Form, Head, Link } from '@inertiajs/react';
import {
    AuthFootnote,
    AuthNotice,
    authLink,
    authPrimaryButton,
} from '@/components/auth/auth-ui';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <div className="flex flex-col gap-7">
            <Head title="Forgot password" />

            {status ? <AuthNotice tone="success">{status}</AuthNotice> : null}

            <Form {...email.form()} className="flex flex-col gap-7">
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="email"
                            type="email"
                            name="email"
                            variant="line"
                            label="Email"
                            placeholder="you@example.com"
                            autoComplete="off"
                            autoFocus
                            error={errors.email}
                        />

                        <Button
                            type="submit"
                            className={authPrimaryButton}
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Email reset link
                        </Button>
                    </>
                )}
            </Form>

            <AuthFootnote>
                Remembered it?{' '}
                <Link href={login()} className={authLink}>
                    Back to sign in
                </Link>
            </AuthFootnote>
        </div>
    );
}

ForgotPassword.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Account',
        title: 'Forgot password',
        description:
            'Enter the email you signed up with. We will send you a link to choose a new password.',
    },
];
