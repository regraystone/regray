import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import EmailPreferencesController from '@/actions/App/Http/Controllers/Settings/EmailPreferencesController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import ConnectedAccounts, {
    type GoogleConnection,
} from '@/components/connected-accounts';
import DeleteUser from '@/components/delete-user';
import TermsDialog from '@/components/terms-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { show as showProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { send } from '@/routes/verification';
import type { SocialNotice } from '@/lib/auth-schema';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

type EmailPreferences = {
    marketingEmails: boolean;
    /** When the user agreed to the terms at sign-up; null for an account made otherwise. */
    termsAcceptedAt: string | null;
    /** The terms' current version, shown in their dialog (config/legal.php). */
    termsVersion: string;
};

export default function AccountSecurity({
    mustVerifyEmail,
    status,
    emailPreferences,
    google,
    socialNotice,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    emailPreferences: EmailPreferences;
    google: GoogleConnection;
    socialNotice?: SocialNotice | null;
}) {
    const { auth } = usePage<PageProps>().props;
    const [marketingEmails, setMarketingEmails] = useState(
        emailPreferences.marketingEmails,
    );
    const [termsOpen, setTermsOpen] = useState(false);

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

                    {/* Email Preferences */}
                    <Card className="border-border py-4 shadow-none sm:py-6">
                        <CardHeader className="px-4 sm:px-6">
                            <CardTitle>Email preferences</CardTitle>
                            <CardDescription>
                                Choose whether we email you. Emails about your
                                account itself, such as verifying your address
                                or resetting your password, are always sent.
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="px-4 sm:px-6">
                            <Form
                                {...EmailPreferencesController.update.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                className="space-y-6"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        {/* The box's state, sent as a value either way. */}
                                        <input
                                            type="hidden"
                                            name="marketing_emails"
                                            value={marketingEmails ? '1' : '0'}
                                        />

                                        <div>
                                            <div className="flex items-start gap-2">
                                                <Checkbox
                                                    id="marketing_emails"
                                                    className="mt-0.5"
                                                    checked={marketingEmails}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setMarketingEmails(
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <label
                                                    htmlFor="marketing_emails"
                                                    className="text-sm text-secondary-foreground"
                                                >
                                                    Email me about new messages
                                                    and updates (optional)
                                                </label>
                                            </div>
                                            {errors.marketing_emails ? (
                                                <p
                                                    role="alert"
                                                    className="mt-2 text-[0.8rem] font-semibold text-secondary-foreground"
                                                >
                                                    {errors.marketing_emails}
                                                </p>
                                            ) : null}
                                        </div>

                                        {emailPreferences.termsAcceptedAt !==
                                        null ? (
                                            <p className="text-xs text-muted-foreground">
                                                You agreed to the{' '}
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setTermsOpen(true)
                                                    }
                                                    className="underline underline-offset-4"
                                                >
                                                    Terms &amp; Conditions
                                                </button>{' '}
                                                on{' '}
                                                {new Date(
                                                    emailPreferences.termsAcceptedAt,
                                                ).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        dateStyle: 'long',
                                                    },
                                                )}
                                                .
                                            </p>
                                        ) : null}

                                        <div>
                                            <Button
                                                disabled={processing}
                                                data-test="update-email-preferences-button"
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

                    {/* Connected Accounts */}
                    <ConnectedAccounts google={google} notice={socialNotice} />

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

            <TermsDialog
                open={termsOpen}
                onOpenChange={setTermsOpen}
                version={emailPreferences.termsVersion}
            />
        </>
    );
}
