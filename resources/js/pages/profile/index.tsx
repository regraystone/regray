import { Head } from '@inertiajs/react';
import { Calendar, Receipt, Settings } from 'lucide-react';
import { toast } from 'sonner';
import { HubRow, HubSection } from '@/components/hub-rows';
import PageHeading from '@/components/page-heading';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import {
    schedule as profileSchedule,
    settings as profileSettings,
} from '@/routes/profile';

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

                    <HubSection title="Transaction history">
                        <HubRow
                            icon={Receipt}
                            title="Transaction history"
                            description="View your payments and past transactions"
                            onClick={() => {
                                toast.info(
                                    'Transaction history is coming soon.',
                                );
                            }}
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
