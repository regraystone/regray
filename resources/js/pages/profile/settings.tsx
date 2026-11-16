import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowLeftRight,
    LifeBuoy,
    LogOut,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { HubRow, HubSection } from '@/components/hub-rows';
import LogoutDialog from '@/components/logout-dialog';
import PageHeading from '@/components/page-heading';
import {
    edit as editAccountSecurity,
    show as showProfile,
} from '@/routes/profile';

/**
 * The account-level options, one tap from the Profile hub, in three groups:
 * the account itself, where to get help, and this session. Help & Support and
 * Switch account are not built yet, so their rows only say so. Log out asks
 * first. Every reader of the profile, client or staff, has all of them.
 */
const Settings = () => {
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    return (
        <>
            <Head title="Settings" />

            <h1 className="sr-only">Settings</h1>

            <div className="mx-auto w-full max-w-2xl">
                {/* Mobile back link */}
                <Link
                    href={showProfile.url()}
                    className="mb-5 inline-flex items-center gap-2 text-sm text-secondary-foreground transition-opacity hover:opacity-70 sm:hidden"
                >
                    <ArrowLeft className="size-4" aria-hidden />
                    Back to profile
                </Link>

                <PageHeading
                    title="Settings"
                    description="Your account, help and this session"
                />

                <div className="space-y-6 sm:space-y-8">
                    <HubSection title="Account">
                        <HubRow
                            icon={ShieldCheck}
                            title="Account & Security"
                            description="Your name, email, password and two-factor authentication"
                            href={editAccountSecurity.url()}
                        />
                    </HubSection>

                    <HubSection title="Support">
                        <HubRow
                            icon={LifeBuoy}
                            title="Help & Support"
                            description="Get support, guides, and answers to common questions"
                            onClick={() => {
                                toast.info('Help & Support is coming soon.');
                            }}
                            dataTest="help-support-button"
                        />
                    </HubSection>

                    {/* Leaving the session is kept apart from the rest. */}
                    <HubSection title="Session">
                        <HubRow
                            icon={ArrowLeftRight}
                            title="Switch account"
                            description="Sign in with a different account"
                            onClick={() => {
                                toast.info('Switch account is coming soon.');
                            }}
                            dataTest="switch-account-button"
                        />
                        <HubRow
                            icon={LogOut}
                            title="Log out"
                            description="End this session on this device"
                            onClick={() => setIsLoggingOut(true)}
                            dataTest="logout-button"
                        />
                    </HubSection>
                </div>
            </div>

            <LogoutDialog open={isLoggingOut} onOpenChange={setIsLoggingOut} />
        </>
    );
};

export default Settings;
