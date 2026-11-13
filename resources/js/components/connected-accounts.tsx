import { Form } from '@inertiajs/react';
import GoogleController from '@/actions/App/Http/Controllers/Auth/GoogleController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { SocialNotice } from '@/lib/auth-schema';

export type GoogleConnection = {
    /** Whether the app has a Google OAuth client to connect with. */
    available: boolean;
    connected: boolean;
    /** The Google account's email while connected. */
    connectedEmail: string | null;
};

/**
 * Connect Google to the account, or disconnect it. Connecting is a trip to
 * Google and back, so it is a plain link; disconnecting stays on the page.
 */
const ConnectedAccounts = ({
    google,
    notice,
}: {
    google: GoogleConnection;
    notice?: SocialNotice | null;
}) => (
    <Card className="border-border py-4 shadow-none sm:py-6">
        <CardHeader className="px-4 sm:px-6">
            <CardTitle>Connected accounts</CardTitle>
            <CardDescription>
                Sign in with Google as well as your password
            </CardDescription>
        </CardHeader>

        <CardContent className="space-y-4 px-4 sm:px-6">
            {notice ? (
                <p
                    role={notice.type === 'error' ? 'alert' : 'status'}
                    className="border border-border bg-muted/30 p-3 text-sm font-medium text-secondary-foreground"
                    data-test="social-notice"
                >
                    {notice.message}
                </p>
            ) : null}

            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0 space-y-0.5">
                    <div className="text-sm font-bold">Google</div>
                    <div className="truncate text-sm text-secondary-foreground">
                        {google.connected
                            ? `Connected as ${google.connectedEmail ?? 'your Google account'}`
                            : 'Not connected'}
                    </div>
                </div>

                {google.connected ? (
                    <Form
                        {...GoogleController.disconnect.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                variant="outline"
                                disabled={processing}
                                className="w-full sm:w-auto"
                                data-test="google-disconnect"
                            >
                                Disconnect
                            </Button>
                        )}
                    </Form>
                ) : google.available ? (
                    <Button asChild className="w-full sm:w-auto">
                        {/* A plain link: the browser goes to Google and back. */}
                        <a
                            href={GoogleController.connect.url()}
                            data-test="google-connect"
                        >
                            Connect Google
                        </a>
                    </Button>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Not available yet
                    </span>
                )}
            </div>

            {google.connected ? (
                <p className="text-xs text-muted-foreground">
                    Made your account with Google? Use "Forgot password" on the
                    Sign In page to set a password before disconnecting, or you
                    won't be able to sign in.
                </p>
            ) : null}
        </CardContent>
    </Card>
);

export default ConnectedAccounts;
