import { Form, Head } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import ManageTwoFactor from '@/components/manage-two-factor';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { PasswordField } from '@/components/ui/password-field';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManageTwoFactorProps;

export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-6">
                <Card className="rounded-none border-border shadow-none">
                    <CardHeader>
                        <CardTitle>Update password</CardTitle>
                        <CardDescription>
                            Ensure your account is using a long, random password
                            to stay secure
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <Form
                            {...SecurityController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnError={[
                                'password',
                                'password_confirmation',
                                'current_password',
                            ]}
                            resetOnSuccess
                            onError={(errors) => {
                                if (errors.password) {
                                    passwordInput.current?.focus();
                                }

                                if (errors.current_password) {
                                    currentPasswordInput.current?.focus();
                                }
                            }}
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <PasswordField
                                        id="current_password"
                                        type="password"
                                        name="current_password"
                                        label="Current password"
                                        placeholder="Current password"
                                        autoComplete="current-password"
                                        ref={currentPasswordInput}
                                        error={errors.current_password}
                                    />

                                    <PasswordField
                                        id="password"
                                        type="password"
                                        name="password"
                                        label="New password"
                                        placeholder="New password"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                        ref={passwordInput}
                                        error={errors.password}
                                    />

                                    <PasswordField
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        label="Confirm password"
                                        placeholder="Confirm password"
                                        autoComplete="new-password"
                                        passwordrules={props.passwordRules}
                                        error={errors.password_confirmation}
                                    />

                                    <div className="flex items-center gap-4">
                                        <Button
                                            disabled={processing}
                                            data-test="update-password-button"
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="rounded-none border-border shadow-none">
                    <CardHeader>
                        <CardTitle>Two-factor authentication</CardTitle>
                        <CardDescription>
                            Manage your two-factor authentication settings
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <ManageTwoFactor
                            canManageTwoFactor={props.canManageTwoFactor}
                            requiresConfirmation={props.requiresConfirmation}
                            twoFactorEnabled={props.twoFactorEnabled}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
