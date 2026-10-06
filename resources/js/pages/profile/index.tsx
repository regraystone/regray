import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    ChevronRight,
    HelpCircle,
    LogOut,
    ShieldCheck,
} from 'lucide-react';
import PageHeading from '@/components/page-heading';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { logout } from '@/routes';
import {
    agenda as profileAgenda,
    edit as editAccountSecurity,
} from '@/routes/profile';

/**
 * Your account.
 *
 * Designed mobile-first as a settings hub with labeled sections, grouped
 * rows, and a quiet, separated logout affordance.
 *
 * The agenda is listed here when this reader may reach it, and nowhere else: a
 * client has no Agenda in the sidebar and comes through this page, while the
 * staff who read the week have the sidebar entry and are not offered this door.
 * Which of the two a role gets is a permission question — `profile-agenda.view`
 * against `agenda.view` — so nothing here names a role.
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
                    {/* Agenda section */}
                    {can(permissions.profileAgendaView) && (
                        <div>
                            <h3 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                                Agenda
                            </h3>

                            <div className="border border-border bg-background">
                                <Link
                                    href={profileAgenda.url()}
                                    className="group flex min-h-[56px] items-center justify-between gap-4 p-4 transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                                >
                                    <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                                        <Calendar
                                            className="size-5 shrink-0 text-secondary-foreground"
                                            aria-hidden
                                        />
                                        <div className="min-w-0 space-y-0.5">
                                            <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                                                Agenda
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground sm:text-sm">
                                                Your showroom visits and when to
                                                expect them
                                            </div>
                                        </div>
                                    </div>

                                    <ChevronRight
                                        className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                                        aria-hidden
                                    />
                                </Link>
                            </div>
                        </div>
                    )}

                    {/* Account & Security section */}
                    <div>
                        <h3 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                            Account &amp; Security
                        </h3>

                        <div className="border border-border bg-background">
                            <Link
                                href={editAccountSecurity.url()}
                                className="group flex min-h-[56px] items-center justify-between gap-4 p-4 transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                            >
                                <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                                    <ShieldCheck
                                        className="size-5 shrink-0 text-secondary-foreground"
                                        aria-hidden
                                    />
                                    <div className="min-w-0 space-y-0.5">
                                        <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                                            Account &amp; Security
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground sm:text-sm">
                                            Update your name, email address,
                                            password and two-factor
                                            authentication
                                        </div>
                                    </div>
                                </div>

                                <ChevronRight
                                    className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                                    aria-hidden
                                />
                            </Link>
                        </div>
                    </div>

                    {/* Help section */}
                    <div>
                        <h3 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                            Help
                        </h3>

                        <div className="border border-border bg-background">
                            <Link
                                href="#"
                                onClick={(e) => e.preventDefault()}
                                className="group flex min-h-[56px] items-center justify-between gap-4 p-4 transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                            >
                                <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                                    <HelpCircle
                                        className="size-5 shrink-0 text-secondary-foreground"
                                        aria-hidden
                                    />
                                    <div className="min-w-0 space-y-0.5">
                                        <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                                            Help
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground sm:text-sm">
                                            Get support, guides, and answers to
                                            common questions
                                        </div>
                                    </div>
                                </div>

                                <ChevronRight
                                    className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                                    aria-hidden
                                />
                            </Link>
                        </div>
                    </div>

                    {/*
                        Signing out is separated from primary settings navigation,
                        preserving hit target size while presenting a quieter affordance.
                    */}
                    <div>
                        <h3 className="mb-2 text-[0.68rem] font-bold tracking-[0.16em] text-secondary-foreground uppercase">
                            Session
                        </h3>

                        <Link
                            href={logout()}
                            as="button"
                            onClick={() => router.flushAll()}
                            data-test="logout-button"
                            className="group flex min-h-[56px] w-full cursor-pointer items-center justify-between gap-4 border border-border bg-background p-4 text-left transition-colors hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:p-5"
                        >
                            <div className="flex min-w-0 items-center gap-3.5 sm:gap-4">
                                <LogOut
                                    className="size-5 shrink-0 text-secondary-foreground"
                                    aria-hidden
                                />
                                <div className="min-w-0 space-y-0.5">
                                    <div className="text-sm font-bold tracking-[-0.02em] text-foreground sm:text-base">
                                        Log out
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground sm:text-sm">
                                        End this session on this device
                                    </div>
                                </div>
                            </div>

                            <ChevronRight
                                className="size-5 shrink-0 text-secondary-foreground transition-transform duration-200 group-hover:translate-x-0.5"
                                aria-hidden
                            />
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
};

export default Profile;
