import { Head } from '@inertiajs/react';
import {
    BadgeCheck,
    Bug,
    Calendar,
    Coins,
    CreditCard,
    FileText,
    Settings,
    UserRound,
} from 'lucide-react';
import { HubRow, HubSection } from '@/components/hub-rows';
import PageHeading from '@/components/page-heading';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import {
    schedule as profileSchedule,
    settings as profileSettings,
} from '@/routes/profile';
import { edit as editClientInformation } from '@/routes/profile/client-information';
import { comingSoon } from '@/lib/coming-soon';

/**
 * Your account.
 *
 * Designed mobile-first as a hub with labeled sections and grouped rows. The
 * account-level options (Account & Security, Help & Support, Switch account
 * and Log out) are one tap further, under Settings.
 *
 * The schedule is listed here when this reader may reach it, and nowhere else: a
 * client has no Schedule in the sidebar and comes through this page, while the
 * staff who read the week have the sidebar entry and are not offered this door.
 * Which of the two a role gets is a permission question — `profile-schedule.view`
 * against `schedule.view` — so nothing here names a role.
 *
 * Placeholder rows (Payments, Quotations, Warranties, Points and Report a Bug)
 * are UI stubs that display a "coming soon" toast when clicked.
 */
const Profile = () => {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Profile" />

            <h1 className="sr-only">Profile</h1>

            <div className="mx-auto w-full max-w-2xl">
                <PageHeading title="Profile" description="Your account" />

                <div className="space-y-6 sm:space-y-8">
                    {can(permissions.profileScheduleView) && (
                        <HubSection title="Schedule">
                            <HubRow
                                icon={Calendar}
                                title="Schedule"
                                description="Your appointments and when to expect them"
                                href={profileSchedule.url()}
                            />
                        </HubSection>
                    )}

                    {can(permissions.profileClientInformationView) && (
                        <HubSection title="Your details">
                            <HubRow
                                icon={UserRound}
                                title="Client Information"
                                description="How you'd like to get it and where"
                                href={editClientInformation.url()}
                                dataTest="profile-client-information-link"
                            />
                        </HubSection>
                    )}

                    <HubSection title="Activity">
                        <HubRow
                            icon={CreditCard}
                            title="Payments"
                            description="Your transaction records"
                            onClick={comingSoon}
                            dataTest="profile-payments-button"
                        />
                        <HubRow
                            icon={FileText}
                            title="Quotations"
                            description="Your submitted requests"
                            onClick={comingSoon}
                            dataTest="profile-quotations-button"
                        />
                        <HubRow
                            icon={BadgeCheck}
                            title="Warranties"
                            description="Your coverage and terms"
                            onClick={comingSoon}
                            dataTest="profile-warranties-button"
                        />
                        <HubRow
                            icon={Coins}
                            title="Points"
                            description="Your reward balance"
                            onClick={comingSoon}
                            dataTest="profile-points-button"
                        />
                    </HubSection>

                    <HubSection title="Feedback">
                        <HubRow
                            icon={Bug}
                            title="Report a Bug"
                            description="Report issues and feedback"
                            onClick={comingSoon}
                            dataTest="profile-report-bug-button"
                        />
                    </HubSection>

                    <HubSection title="Settings">
                        <HubRow
                            icon={Settings}
                            title="Settings"
                            description="Account & security, help & support, and your session"
                            href={profileSettings.url()}
                            dataTest="profile-settings-link"
                        />
                    </HubSection>
                </div>
            </div>
        </>
    );
};

export default Profile;
