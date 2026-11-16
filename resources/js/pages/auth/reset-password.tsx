import { Form, Head } from '@inertiajs/react';
import { authPrimaryButton } from '@/components/auth/auth-ui';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { PasswordField } from '@/components/ui/password-field';
import { Spinner } from '@/components/ui/spinner';
import AuthBrandLayout from '@/layouts/auth/auth-brand-layout';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Reset password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-7"
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            id="email"
                            type="email"
                            name="email"
                            variant="line"
                            label="Email"
                            autoComplete="email"
                            value={email}
                            readOnly
                            error={errors.email}
                        />

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
                            label="Confirm new password"
                            autoComplete="new-password"
                            passwordrules={passwordRules}
                            error={errors.password_confirmation}
                        />

                        <Button
                            type="submit"
                            className={authPrimaryButton}
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Reset password
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = [
    AuthBrandLayout,
    {
        eyebrow: 'Account',
        title: 'Choose a new password',
        description:
            'Set a new password for your account, then sign in with it.',
    },
];
