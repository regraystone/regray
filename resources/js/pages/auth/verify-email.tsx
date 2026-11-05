import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import LogoutDialog from '@/components/logout-dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    return (
        <>
            <Head title="Email verification" />

            <div className="w-full">
                {status === 'verification-link-sent' ? (
                    <p className="mb-6 text-center text-[0.9rem] leading-relaxed text-secondary-foreground">
                        A new verification link has been sent to the email
                        address you provided during registration.
                    </p>
                ) : null}

                <Form {...send.form()} className="flex flex-col gap-6">
                    {({ processing }) => (
                        <>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Resend verification email
                            </Button>

                            <button
                                type="button"
                                onClick={() => setIsLoggingOut(true)}
                                className="cursor-pointer text-center text-[0.8rem] font-semibold text-secondary-foreground transition-opacity hover:opacity-85"
                            >
                                Log out
                            </button>
                        </>
                    )}
                </Form>
            </div>

            <LogoutDialog open={isLoggingOut} onOpenChange={setIsLoggingOut} />
        </>
    );
}

VerifyEmail.layout = [
    AuthSheetLayout,
    {
        title: 'Email verification',
        description:
            'Please verify your email address by clicking on the link we just emailed to you.',
    },
];
