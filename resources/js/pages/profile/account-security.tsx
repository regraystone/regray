import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, ShieldCheck } from 'lucide-react';
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
import { show as showProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function AccountSecurity({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Account &amp; Security" />

            <h1 className="sr-only">Account &amp; Security</h1>

            <div className="mx-auto w-full max-w-2xl">
                {/* Mobile back link */}
                <Link
                    href={showProfile.url()}
                    className="mb-5 inline-flex items-center gap-2 text-sm text-secondary-foreground transition-opacity hover:opacity-70 sm:hidden"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    Back to profile
                </Link>

                <div className="space-y-6">
                    {/* Profile Information Form */}
                    <Card className="border-border py-4 shadow-none sm:py-6">
                        <CardHeader className="px-4 sm:px-6">
                            <CardTitle>Profile</CardTitle>
                            <CardDescription>
                                Update your name and email address
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="px-4 sm:px-6">
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
                                            inputMode="email"
                                            name="email"
                                            defaultValue={auth.user.email}
                                            required
                                            autoComplete="email"
                                            placeholder="Email address"
                                            error={errors.email}
                                        />

                                        {mustVerifyEmail &&
                                            auth.user.email_verified_at ===
                                                null && (
                                                <div className="space-y-1.5 border border-border bg-muted/30 p-3 sm:p-4">
                                                    <p className="text-sm text-secondary-foreground">
                                                        Your email address is
                                                        unverified.{' '}
                                                        <Link
                                                            href={send()}
                                                            as="button"
                                                            className="font-medium text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current"
                                                        >
                                                            Click here to
                                                            re-send the
                                                            verification email.
                                                        </Link>
                                                    </p>

                                                    {status ===
                                                        'verification-link-sent' && (
                                                        <div className="text-sm font-medium text-primary">
                                                            A new verification
                                                            link has been sent
                                                            to your email
                                                            address.
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                        <div>
                                            <Button
                                                disabled={processing}
                                                data-test="update-profile-button"
                                                className="w-full sm:w-auto"
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Security Door */}
                    <Link
                        href={editSecurity.url()}
                        className="group flex min-h-[56px] items-center justify-between gap-4 border border-border bg-background p-4 text-left transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                    >
                        <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                            <ShieldCheck
                                className="size-5 shrink-0 text-secondary-foreground"
                                aria-hidden
                            />
                            <div className="min-w-0 space-y-0.5">
                                <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                                    Security
                                </div>
                                <div className="truncate text-xs text-muted-foreground sm:text-sm">
                                    Update your password and manage two-factor
                                    authentication
                                </div>
                            </div>
                        </div>

                        <ChevronRight
                            className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                            aria-hidden
                        />
                    </Link>

                    {/* Delete Account */}
                    <Card className="border-border py-4 shadow-none sm:py-6">
                        <CardHeader className="px-4 sm:px-6">
                            <CardTitle>Delete account</CardTitle>
                            <CardDescription>
                                Delete your account and all of its resources
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="px-4 sm:px-6">
                            <DeleteUser />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
