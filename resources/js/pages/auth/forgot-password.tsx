import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import TextLink from '@/components/text-link';
import AuthSheetLayout from '@/layouts/auth/auth-sheet-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="space-y-6 max-md:space-y-(--auth-block-gap)">
                <Form
                    {...email.form()}
                    className="space-y-5 max-md:space-y-(--auth-field-gap)"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                id="email"
                                type="email"
                                name="email"
                                label="Email"
                                placeholder="email@example.com"
                                autoComplete="off"
                                autoFocus
                                error={errors.email}
                            />

                            <Button
                                type="submit"
                                className="mt-1 w-full max-md:mt-(--auth-block-gap)"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Email password reset link
                            </Button>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Or, return to</span>
                    <TextLink href={login()}>log in</TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = [
    AuthSheetLayout,
    {
        title: 'Forgot password',
        description: 'Enter your email to receive a password reset link',
    },
];
