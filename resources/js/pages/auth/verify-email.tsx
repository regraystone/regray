import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AuthFootnote,
    AuthNotice,
    authLink,
    authPrimaryButton,
} from '@/components/auth/auth-ui';
import LogoutDialog from '@/components/logout-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

export default function VerifyEmail({ status }: { status?: string }) {
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const { auth } = usePage<{ auth: Auth }>().props;

    return (
        <div className="flex flex-col gap-7">
            <Head title="Email verification" />

            {status === 'verification-link-sent' ? (
                <AuthNotice tone="success">
                    A new verification link has been sent to your email address.
                </AuthNotice>
            ) : null}

            <div className="flex flex-col gap-1.5 border-y border-line py-5">
                <span className="text-[0.9375rem] font-semibold text-ink-soft">
                    Sent to
                </span>
                <span className="text-lg break-all">{auth.user.email}</span>
            </div>

            <p className="text-[0.9375rem] leading-relaxed text-ink-soft">
                Can&rsquo;t find it? Check your spam folder, or send a new link.
            </p>

            <Form {...send.form()}>
                {({ processing }) => (
                    <Button
                        type="submit"
                        className={authPrimaryButton}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Resend verification email
                    </Button>
                )}
            </Form>

            <AuthFootnote>
                Wrong account?{' '}
                <button
                    type="button"
                    onClick={() => setIsLoggingOut(true)}
                    className={`cursor-pointer ${authLink}`}
                >
                    Log out
                </button>
            </AuthFootnote>

            <LogoutDialog open={isLoggingOut} onOpenChange={setIsLoggingOut} />
        </div>
    );
}

VerifyEmail.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'One more step',
        title: 'Verify your email',
        description:
            'We sent a link to your inbox. Open it to confirm your address and finish setting up your account.',
    },
];
