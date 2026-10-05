import { Head, Link, router } from '@inertiajs/react';
import { Calendar, ChevronRight, LogOut, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import PageHeading from '@/components/page-heading';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { logout } from '@/routes';
import {
    agenda as profileAgenda,
    edit as editAccountSecurity,
} from '@/routes/profile';

/**
 * One door on the Profile page.
 *
 * The same shape as the Security card on Account & Security: a title, a line
 * saying what is behind it, and a chevron, so the hub and the page under it are
 * read as the same list of places rather than two different designs.
 */
type HubCard = {
    title: string;
    description: string;
    icon: LucideIcon;
    href: string;
};

/**
 * Your account.
 *
 * A hub rather than a page of settings, so the Profile item in the sidebar has
 * somewhere to point that is not one particular set of fields. Every door below
 * is a page of its own and decides for itself what it shows.
 *
 * The agenda is listed here when this reader may reach it, and nowhere else: a
 * client has no Agenda in the sidebar and comes through this page, while the
 * staff who read the week have the sidebar entry and are not offered this door.
 * Which of the two a role gets is a permission question — `profile-agenda.view`
 * against `agenda.view` — so nothing here names a role.
 */
const Profile = () => {
    const { can } = usePermissions();

    const cards: HubCard[] = [];

    if (can(permissions.profileAgendaView)) {
        cards.push({
            title: 'Agenda',
            description: 'Your showroom visits and when to expect them',
            icon: Calendar,
            href: profileAgenda.url(),
        });
    }

    cards.push({
        title: 'Account & Security',
        description:
            'Update your name, email address, password and two-factor authentication',
        icon: ShieldCheck,
        href: editAccountSecurity.url(),
    });

    return (
        <>
            <Head title="Profile" />

            <h1 className="sr-only">Profile</h1>

            <div className="mx-auto w-full max-w-6xl">
                <PageHeading title="Profile" description="Your account" />

                <div className="space-y-4">
                    {cards.map((card) => (
                        <Link
                            key={card.title}
                            href={card.href}
                            className="block transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <Card className="rounded-none border-border shadow-none">
                                <CardHeader className="flex-row items-center justify-between gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <CardTitle className="flex items-center gap-2">
                                            <card.icon
                                                className="size-4 shrink-0 text-secondary-foreground"
                                                aria-hidden
                                            />
                                            {card.title}
                                        </CardTitle>
                                        <CardDescription>
                                            {card.description}
                                        </CardDescription>
                                    </div>

                                    <ChevronRight
                                        className="size-5 shrink-0 text-secondary-foreground"
                                        aria-hidden
                                    />
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}

                    {/*
                        Signing out is here rather than left to the avatar menu
                        alone, because this is the page a reader lands on when they
                        came to end the session. Same link, same flush, as the one
                        in the header.
                    */}
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                        className="block w-full cursor-pointer transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <Card className="rounded-none border-border shadow-none">
                            <CardHeader className="flex-row items-center justify-between gap-4">
                                <div className="flex flex-col gap-1.5">
                                    <CardTitle className="flex items-center gap-2">
                                        <LogOut
                                            className="size-4 shrink-0 text-secondary-foreground"
                                            aria-hidden
                                        />
                                        Log out
                                    </CardTitle>
                                    <CardDescription>
                                        End this session on this device
                                    </CardDescription>
                                </div>

                                <ChevronRight
                                    className="size-5 shrink-0 text-secondary-foreground"
                                    aria-hidden
                                />
                            </CardHeader>
                        </Card>
                    </Link>
                </div>
            </div>
        </>
    );
};

export default Profile;
