import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Bug,
    Calendar,
    FileText,
    Gift,
    Heart,
    MapPin,
    Pencil,
    ReceiptText,
    Settings,
    ShieldCheck,
    UserRound,
    type LucideIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { HubRow, HubSection } from '@/components/hub-rows';
import ProfilePhoto from '@/components/profile-photo';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { comingSoon } from '@/lib/coming-soon';
import {
    edit as editAccountSecurity,
    schedule as profileSchedule,
    settings as profileSettings,
} from '@/routes/profile';
import { index as profileAddresses } from '@/routes/profile/addresses';
import { index as quoteIndex } from '@/routes/quote';
import { index as savedIndex } from '@/routes/saved';
import type { User } from '@/types/auth';

/** What ProfileController sends beside the shared props (ProfileSummaryAction). */
type ProfileSummary = {
    phone: string | null;
    company: string | null;
    /** Null for a reader with no Quote list. */
    openQuotations: number | null;
    /** Null for a reader with no Addresses page. */
    hasDefaultAddress: boolean | null;
};

/** What a staff role is called on the profile; a client's account shows none. */
const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    owner: 'Owner',
    marketing: 'Marketing',
    sales: 'Sales Representative',
    technical: 'Technical Staff',
    accounting: 'Accounting',
    logistics: 'Logistics',
};

const plural = (count: number, one: string, many: string) =>
    `${count} ${count === 1 ? one : many}`;

/** A short word in a pill beside a title: a count, Soon or Coming soon. */
const Pill = ({ children }: { children: ReactNode }) => (
    <span className="rounded-full border border-border bg-background px-2 py-0.5 text-[0.625rem] font-semibold tracking-[0.06em] whitespace-nowrap text-muted-foreground uppercase">
        {children}
    </span>
);

const cardButton =
    'mt-auto inline-flex items-center gap-2 self-start px-4 py-2.5 text-[0.6875rem] font-bold tracking-[0.08em] uppercase transition-colors';

/**
 * One of Activity's cards: its icon, title and pill, a line on what it holds
 * and its button, a link when the page exists, otherwise "coming soon".
 */
const ActivityCard = ({
    icon: Icon,
    title,
    pill,
    description,
    action,
    href,
    dataTest,
}: {
    icon: LucideIcon;
    title: string;
    pill?: string;
    description: string;
    action: string;
    href?: string;
    dataTest?: string;
}) => (
    <div className="flex flex-col gap-3 p-5 sm:p-6">
        <div className="flex flex-wrap items-center gap-2">
            <Icon className="size-4 shrink-0" aria-hidden />
            <h4 className="text-sm font-bold tracking-[-0.02em] sm:text-base">
                {title}
            </h4>
            {pill ? <Pill>{pill}</Pill> : null}
        </div>
        <p className="mb-2 text-sm text-muted-foreground">{description}</p>
        {href ? (
            <Link
                href={href}
                className={`${cardButton} bg-foreground text-background hover:bg-foreground/80`}
                data-test={dataTest}
            >
                {action}
                <ArrowRight className="size-3.5" aria-hidden />
            </Link>
        ) : (
            <button
                type="button"
                onClick={comingSoon}
                className={`${cardButton} cursor-pointer border border-foreground hover:bg-foreground hover:text-background`}
                data-test={dataTest}
            >
                {action}
                <ArrowRight className="size-3.5" aria-hidden />
            </button>
        )}
    </div>
);

/**
 * Your account.
 *
 * "My Profile", then who is signed in (their photo, which a client may add,
 * change or remove, or their initials; their name, email, the phone
 * and company they gave and, for staff, their role) with Edit profile, beside
 * Loyalty & Rewards (coming soon). Below, the doors in labelled groups:
 * Account; Activity, three cards in order (Quotations with how many are open,
 * Transaction History and Warranties); Schedule & Locations; Saved &
 * Preferences; Feedback. A door, and a group left empty, is drawn only for a
 * reader who may open it, from the shared `auth.permissions` prop as in the
 * sidebar.
 *
 * The schedule is listed here when this reader may reach it, and nowhere else: a
 * client has no Schedule in the sidebar and comes through this page, while the
 * staff who read the week have the sidebar entry and are not offered this door.
 * Which of the two a role gets is a permission question — `profile-schedule.view`
 * against `schedule.view` — so nothing here names a role.
 *
 * Placeholder doors (Loyalty & Rewards, Transaction History, Warranties and
 * Report a Bug) are marked Soon and display a "coming soon" toast when used.
 */
const Profile = ({ summary }: { summary: ProfileSummary }) => {
    const { can } = usePermissions();
    const getInitials = useInitials();
    const { auth, savedStoneSlugs, savedFurnitureSlugs } = usePage<{
        auth: { user: User; roles: string[]; avatar: string | null };
        savedStoneSlugs: string[];
        savedFurnitureSlugs: string[];
    }>().props;
    const user = auth.user;
    const roles = auth.roles
        .map((role) => ROLE_LABELS[role])
        .filter((label): label is string => label !== undefined);

    const savedParts = [
        savedStoneSlugs.length > 0
            ? plural(savedStoneSlugs.length, 'stone', 'stones')
            : null,
        savedFurnitureSlugs.length > 0
            ? plural(
                  savedFurnitureSlugs.length,
                  'piece of furniture',
                  'pieces of furniture',
              )
            : null,
    ].filter((part): part is string => part !== null);

    const canSchedule = can(permissions.profileScheduleView);
    const canAddresses = can(permissions.profileAddressesView);

    return (
        <>
            <Head title="My Profile" />

            <div className="mx-auto w-full max-w-4xl">
                <div className="mb-8 border-b border-border pb-6 sm:mb-10 sm:pb-8">
                    <h1 className="font-display text-3xl font-light tracking-[-0.01em] sm:text-4xl">
                        My Profile
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Manage your account, quotations and preferences.
                    </p>
                </div>

                <div className="mb-10 grid items-center gap-6 sm:mb-12 lg:grid-cols-2 lg:gap-8">
                    <section
                        aria-label="Your account"
                        className="flex min-w-0 items-center gap-5"
                    >
                        <ProfilePhoto
                            photo={auth.avatar}
                            initials={getInitials(user.name) || 'R'}
                            editable={auth.roles.includes('client')}
                        />
                        <div className="min-w-0 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="truncate font-display text-xl font-normal sm:text-[1.375rem]">
                                    {user.name}
                                </p>
                                {roles.map((role) => (
                                    <Pill key={role}>{role}</Pill>
                                ))}
                            </div>
                            <p className="flex min-w-0 items-center gap-x-1.5 text-sm text-muted-foreground">
                                <span className="truncate">{user.email}</span>
                                {user.email_verified_at ? (
                                    <BadgeCheck
                                        className="size-3.5 shrink-0 text-foreground"
                                        aria-label="Verified"
                                    />
                                ) : null}
                            </p>
                            {summary.phone ? (
                                <p className="text-sm text-muted-foreground">
                                    {summary.phone}
                                </p>
                            ) : null}
                            {summary.company ? (
                                <p className="text-sm text-muted-foreground">
                                    {summary.company}
                                </p>
                            ) : null}
                            <Link
                                href={editAccountSecurity.url()}
                                className="mt-1.5 inline-flex items-center gap-1.5 border-b border-foreground pb-0.5 text-xs font-bold tracking-[0.06em] uppercase transition-opacity hover:opacity-70"
                                data-test="profile-edit-link"
                            >
                                Edit profile
                                <Pencil className="size-3" aria-hidden />
                            </Link>
                        </div>
                    </section>

                    <button
                        type="button"
                        onClick={comingSoon}
                        className="flex cursor-pointer items-center gap-4 border border-border bg-muted/60 p-5 text-left transition-colors hover:bg-muted"
                        data-test="profile-rewards-button"
                    >
                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-background">
                            <Gift className="size-4" aria-hidden />
                        </span>
                        <span className="min-w-0 space-y-1">
                            <span className="flex flex-wrap items-center gap-2 font-display text-base">
                                Loyalty &amp; Rewards
                                <Pill>Coming soon</Pill>
                            </span>
                            <span className="block text-xs text-muted-foreground sm:text-sm">
                                Earn points, unlock member perks, exclusive
                                events and special offers.
                            </span>
                        </span>
                    </button>
                </div>

                <div className="space-y-9 sm:space-y-10">
                    <HubSection title="Account">
                        <HubRow
                            icon={UserRound}
                            title="Profile Details"
                            description="Your name, email, password and sign-in security"
                            href={editAccountSecurity.url()}
                            dataTest="profile-details-link"
                        />
                    </HubSection>

                    <section>
                        <h3 className="mb-3 text-[0.6875rem] font-bold tracking-[0.16em] text-foreground uppercase">
                            Activity
                        </h3>
                        <div
                            className={`grid divide-y divide-border border border-border md:divide-x md:divide-y-0 ${
                                summary.openQuotations !== null
                                    ? 'md:grid-cols-3'
                                    : 'md:grid-cols-2'
                            }`}
                        >
                            {summary.openQuotations !== null ? (
                                <ActivityCard
                                    icon={FileText}
                                    title="Quotations"
                                    pill={
                                        summary.openQuotations > 0
                                            ? `${summary.openQuotations} Active`
                                            : undefined
                                    }
                                    description="Track your quotation requests and responses."
                                    action="View quotations"
                                    href={quoteIndex.url()}
                                    dataTest="profile-quotations-link"
                                />
                            ) : null}
                            <ActivityCard
                                icon={ReceiptText}
                                title="Transaction History"
                                pill="Soon"
                                description="Your payments and refunds in one place."
                                action="View transactions"
                                dataTest="profile-transactions-button"
                            />
                            <ActivityCard
                                icon={ShieldCheck}
                                title="Warranties"
                                pill="Soon"
                                description="Your coverage and terms."
                                action="View warranties"
                                dataTest="profile-warranties-button"
                            />
                        </div>
                    </section>

                    {canSchedule || canAddresses ? (
                        <HubSection
                            title="Schedule & Locations"
                            grid={canSchedule && canAddresses}
                        >
                            {canSchedule ? (
                                <HubRow
                                    icon={Calendar}
                                    title="Schedule"
                                    description="Your appointments and when to expect them"
                                    href={profileSchedule.url()}
                                />
                            ) : null}
                            {canAddresses ? (
                                <HubRow
                                    icon={MapPin}
                                    title="Delivery Addresses"
                                    tag={
                                        summary.hasDefaultAddress
                                            ? '★ Default'
                                            : undefined
                                    }
                                    description="Where we deliver to or install at, and your default"
                                    href={profileAddresses.url()}
                                    dataTest="profile-addresses-link"
                                />
                            ) : null}
                        </HubSection>
                    ) : null}

                    <HubSection
                        title="Saved & Preferences"
                        grid={can(permissions.savedView)}
                    >
                        {can(permissions.savedView) ? (
                            <HubRow
                                icon={Heart}
                                title="Saved Items"
                                description={
                                    savedParts.length > 0
                                        ? savedParts.join(' · ')
                                        : 'The stones and furniture you save'
                                }
                                href={savedIndex.url()}
                                dataTest="profile-saved-link"
                            />
                        ) : null}
                        <HubRow
                            icon={Settings}
                            title="Settings"
                            description="Account, help & support, and your session"
                            href={profileSettings.url()}
                            dataTest="profile-settings-link"
                        />
                    </HubSection>

                    <HubSection title="Feedback">
                        <HubRow
                            icon={Bug}
                            title="Report a Bug"
                            description="Report issues and feedback"
                            onClick={comingSoon}
                            dataTest="profile-report-bug-button"
                            tag="Soon"
                        />
                    </HubSection>
                </div>
            </div>
        </>
    );
};

export default Profile;
