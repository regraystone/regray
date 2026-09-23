import { Form, Head, Link } from '@inertiajs/react';
import AuthButton from '@/components/auth/auth-button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            <div className="w-full max-w-[420px] border border-border bg-background px-6 py-8 sm:px-8">
                {status === 'verification-link-sent' ? (
                    <p className="mb-6 text-center text-[0.9rem] leading-relaxed text-secondary-foreground">
                        A new verification link has been sent to the email
                        address you provided during registration.
                    </p>
                ) : null}

                <Form {...send.form()} className="flex flex-col gap-6">
                    {({ processing }) => (
                        <>
                            <AuthButton type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Resend verification email
                            </AuthButton>

                            <Link
                                href={logout()}
                                className="text-center text-[0.8rem] font-semibold text-secondary-foreground transition-opacity hover:opacity-85"
                            >
                                Log out
                            </Link>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description:
        'Please verify your email address by clicking on the link we just emailed to you.',
};
