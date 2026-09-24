import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Spinner } from '@/components/ui/spinner';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />

            <div className="flex min-h-[60vh] items-center justify-center">
                <Card className="w-full max-w-md rounded-none border-border shadow-none">
                    <CardHeader>
                        <CardTitle>Confirm password</CardTitle>
                        <CardDescription>
                            This is a secure area of the application. Please
                            confirm your password before continuing.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            onError={(errors) => {
                                if (errors.password) {
                                    document
                                        .getElementById('password')
                                        ?.focus();
                                }
                            }}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="password"
                                        type="password"
                                        name="password"
                                        label="Password"
                                        placeholder="Password"
                                        autoComplete="current-password"
                                        autoFocus
                                        error={errors.password}
                                    />

                                    <Button
                                        type="submit"
                                        className="mt-6 w-full"
                                        disabled={processing}
                                        data-test="confirm-password-button"
                                    >
                                        {processing && <Spinner />}
                                        Confirm password
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
