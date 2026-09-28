import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Card className="rounded-none border-border shadow-none">
                    <CardHeader>
                        <CardTitle>Profile</CardTitle>
                        <CardDescription>
                            Update your name and email address
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <Form
                            {...ProfileController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        id="name"
                                        label="Name"
                                        name="name"
                                        defaultValue={auth.user.name}
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                        error={errors.name}
                                    />

                                    <Field
                                        id="email"
                                        label="Email"
                                        type="email"
                                        name="email"
                                        defaultValue={auth.user.email}
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                        error={errors.email}
                                    />

                                    {mustVerifyEmail &&
                                        auth.user.email_verified_at ===
                                            null && (
                                            <div>
                                                <p className="-mt-4 text-sm text-muted-foreground">
                                                    Your email address is
                                                    unverified.{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                                    >
                                                        Click here to re-send
                                                        the verification email.
                                                    </Link>
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <div className="mt-2 text-sm font-medium text-green-600">
                                                        A new verification link
                                                        has been sent to your
                                                        email address.
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                    <div className="flex items-center gap-4">
                                        <Button
                                            disabled={processing}
                                            data-test="update-profile-button"
                                        >
                                            Save
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Link
                    href={editSecurity()}
                    className="block transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card className="rounded-none border-border shadow-none">
                        <CardHeader className="flex-row items-center justify-between gap-4">
                            <div className="flex flex-col gap-1.5">
                                <CardTitle>Security</CardTitle>
                                <CardDescription>
                                    Update your password and manage two-factor
                                    authentication
                                </CardDescription>
                            </div>

                            <ChevronRight
                                className="size-5 shrink-0 text-secondary-foreground"
                                aria-hidden
                            />
                        </CardHeader>
                    </Card>
                </Link>

                <Card className="rounded-none border-border shadow-none">
                    <CardHeader>
                        <CardTitle>Delete account</CardTitle>
                        <CardDescription>
                            Delete your account and all of its resources
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <DeleteUser />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
