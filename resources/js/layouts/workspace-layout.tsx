import { Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import {
    BarChart3,
    Calendar,
    ArrowLeftRight,
    ChevronDown,
    CircleUserRound,
    FileText,
    Folder,
    Handshake,
    Heart,
    HelpCircle,
    Home,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageCircle,
    Package,
    PanelLeftClose,
    PanelLeftOpen,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import Logo from '@/components/logo';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { useSavedStones } from '@/hooks/use-saved-stones';
import { index as accountsIndex } from '@/routes/accounts';
import { index as scheduleIndex } from '@/routes/schedule';
import { index as homeIndex } from '@/routes/home';
import { logout } from '@/routes';
import {
    index as inquiryIndex,
    inquire as inquiryInquire,
} from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as overviewIndex } from '@/routes/overview';
import { index as projectsIndex } from '@/routes/projects';
import { index as stonesIndex } from '@/routes/stones';
import {
    schedule as profileSchedule,
    edit as editAccountSecurity,
    show as showProfile,
} from '@/routes/profile';
import { index as quoteIndex, inquire as quoteInquire } from '@/routes/quote';
import { index as salesIndex } from '@/routes/sales';
import { index as savedIndex } from '@/routes/saved';
import { edit as editSecurity } from '@/routes/security';
import { index as servicesIndex } from '@/routes/services';

/**
 * The permissions the app knows about, mirroring App\Enums\Permission.
 *
 * The server shares the signed-in user's granted names as `auth.permissions`,
 * so the only thing that has to agree between the two sides is the spelling.
 * Keeping the union here means the sidebar's `permission` field is checked
 * against this list rather than being an untyped string.
 *
 * `profileScheduleView` and `homeProjectsView` are listed but no nav item names
 * them: each is the permission for a page reached from another page rather
 * than from the sidebar. `profileScheduleView` is the schedule as reached from the
 * Profile page, and the button for it is drawn there rather than in the
 * sidebar — see the Profile hub page. `homeProjectsView` names the project
 * profile and the stone profile nested under it, both read from Home, where
 * the project cards are drawn by the page that already holds `homeView`.
 */
export const permissions = {
    homeView: 'home.view',
    overviewView: 'overview.view',
    savedView: 'saved.view',
    savedManage: 'saved.manage',
    salesView: 'sales.view',
    quotationsView: 'quotations.view',
    inquiriesView: 'inquiries.view',
    scheduleView: 'schedule.view',
    messagesView: 'messages.view',
    stonesView: 'stones.view',
    servicesView: 'services.view',
    accountsView: 'accounts.view',
    projectsView: 'projects.view',
    profileView: 'profile.view',
    profileScheduleView: 'profile-schedule.view',
    homeProjectsView: 'home-projects.view',
    inquiriesCreate: 'inquiries.create',
    scheduleCreate: 'schedule.create',
    conversationsCreate: 'conversations.create',
    supportClaim: 'support.claim',
} as const;

export type Permission = (typeof permissions)[keyof typeof permissions];

type NavItem = {
    slug: string;
    label: string;
    href: string;
    icon: LucideIcon;
    permission: Permission;
    matchPrefix?: string;
};

const navGroups: Array<{ label: string; items: NavItem[] }> = [
    {
        label: 'Workspace',
        items: [
            {
                slug: 'home',
                label: 'Home',
                href: homeIndex.url(),
                icon: Home,
                permission: permissions.homeView,
            },
            {
                slug: 'overview',
                label: 'Overview',
                href: overviewIndex.url(),
                icon: LayoutDashboard,
                permission: permissions.overviewView,
            },
            {
                slug: 'saved',
                label: 'Saved',
                href: savedIndex.url(),
                icon: Heart,
                permission: permissions.savedView,
            },
        ],
    },
    {
        label: 'Management',
        items: [
            {
                slug: 'sales',
                label: 'Sales',
                href: salesIndex.url(),
                icon: BarChart3,
                permission: permissions.salesView,
            },
            {
                slug: 'quote',
                label: 'Quote',
                href: quoteIndex.url(),
                icon: Handshake,
                permission: permissions.quotationsView,
            },
            {
                slug: 'inquiry',
                label: 'Inquiry',
                href: inquiryIndex.url(),
                icon: FileText,
                permission: permissions.inquiriesView,
            },
            {
                slug: 'schedule',
                label: 'Schedule',
                href: scheduleIndex.url(),
                icon: Calendar,
                permission: permissions.scheduleView,
            },
            {
                slug: 'message',
                label: 'Message',
                href: messageIndex.url(),
                icon: MessageCircle,
                permission: permissions.messagesView,
            },
            {
                slug: 'stones',
                label: 'Stones',
                href: stonesIndex.url(),
                icon: Package,
                permission: permissions.stonesView,
            },
            {
                slug: 'services',
                label: 'Services',
                href: servicesIndex.url(),
                icon: Wrench,
                permission: permissions.servicesView,
            },
            {
                slug: 'projects',
                label: 'Projects',
                href: projectsIndex.url(),
                icon: Folder,
                permission: permissions.projectsView,
            },
            {
                slug: 'accounts',
                label: 'Accounts',
                href: accountsIndex.url(),
                icon: Users,
                permission: permissions.accountsView,
            },
        ],
    },
    {
        label: 'Settings',
        items: [
            {
                slug: 'profile',
                label: 'Profile',
                href: showProfile.url(),
                icon: CircleUserRound,
                permission: permissions.profileView,
                matchPrefix: '/profile',
            },
        ],
    },
];

const allItems = navGroups.flatMap((group) => group.items);

const SIDEBAR_COOKIE_NAME = 'sidebar_state';
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

const navLinkClass = ({
    isActive,
    collapsed,
}: {
    isActive: boolean;
    collapsed: boolean;
}) =>
    `relative flex shrink-0 flex-row items-center overflow-hidden rounded-r-md py-2.5 text-sm transition-opacity hover:opacity-85 ${
        collapsed ? 'justify-center gap-0 px-0' : 'gap-3 px-4'
    } ${
        isActive
            ? 'bg-primary font-bold text-primary-foreground'
            : 'text-secondary-foreground'
    }`;

const SidebarNav = ({
    collapsed,
    pathname,
    savedCount,
    onNavigate,
}: {
    collapsed: boolean;
    pathname: string;
    savedCount: number;
    onNavigate?: () => void;
}) => {
    const { can } = usePermissions();

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => can(item.permission)),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <nav
            className={`flex-1 overflow-y-auto py-5 ${
                collapsed ? 'space-y-6' : 'space-y-1 px-3'
            }`}
        >
            <ul className="space-y-1">
                {visibleGroups.flatMap((group) =>
                    group.items.map((item) => {
                        const isActive = item.matchPrefix
                            ? pathname.startsWith(item.matchPrefix)
                            : pathname === item.href ||
                              pathname.startsWith(`${item.href}/`);

                        return (
                            <li key={item.slug}>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Link
                                            href={item.href}
                                            onClick={onNavigate}
                                            aria-current={
                                                isActive ? 'page' : undefined
                                            }
                                            className={`${navLinkClass({
                                                isActive,
                                                collapsed,
                                            })} w-full`}
                                        >
                                            {isActive && (
                                                <span
                                                    className="absolute inset-y-0 left-0 w-1 bg-primary-foreground"
                                                    aria-hidden
                                                />
                                            )}
                                            <item.icon
                                                className="size-4 shrink-0"
                                                aria-hidden
                                            />
                                            <span
                                                className={`overflow-hidden whitespace-nowrap transition-all duration-300 ${
                                                    collapsed
                                                        ? 'max-w-0 opacity-0'
                                                        : 'max-w-40 opacity-100'
                                                }`}
                                            >
                                                {item.label}
                                            </span>
                                            {item.slug === 'saved' &&
                                                savedCount > 0 &&
                                                !collapsed && (
                                                    <span className="ml-auto grid min-w-5 place-items-center rounded-full bg-foreground/10 px-1.5 py-0.5 text-[0.65rem] font-bold">
                                                        {savedCount}
                                                    </span>
                                                )}
                                        </Link>
                                    </TooltipTrigger>
                                    {collapsed && (
                                        <TooltipContent side="right">
                                            {item.label}
                                        </TooltipContent>
                                    )}
                                </Tooltip>
                            </li>
                        );
                    }),
                )}
            </ul>
        </nav>
    );
};

const WorkspaceLayout = ({ children }: { children: ReactNode }) => {
    const page = usePage();
    const url = page.url.split('?')[0];
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [collapsed, setCollapsed] = useState<boolean>(
        () => !page.props.sidebarOpen,
    );

    const prevUrlRef = useRef<string | null>(null);

    useEffect(() => {
        prevUrlRef.current = url;
    }, [url]);

    // Fortify's confirm-password page is only a gate in front of Security, so
    // the chrome should read as if the user is already on that page.
    const isConfirmPassword = page.component === 'confirm-password';
    const effectiveUrl = isConfirmPassword ? editSecurity.url() : url;

    // Get the stone slug from the page props when on a stone profile page
    const isStoneProfilePage = page.component === 'stone-profile';
    const stoneSlug = isStoneProfilePage
        ? (page.props.stone as { data: { slug: string } } | undefined)?.data
              ?.slug
        : undefined;

    /*
     * The stepper has no sidebar item of its own; it is nested under the list
     * the user reads their inquiries on, so that list is what stays lit.
     */
    const isInquire =
        url === quoteInquire.url() || url === inquiryInquire.url();

    const activePath = isInquire
        ? url.slice(0, url.lastIndexOf('/'))
        : isStoneProfilePage && prevUrlRef.current
          ? prevUrlRef.current
          : effectiveUrl;

    const user = page.props.auth.user;
    const { savedIds, toggleSave, canSave } = useSavedStones();
    const savedCount = savedIds.size;

    const isSaved = isStoneProfilePage && stoneSlug && savedIds.has(stoneSlug);

    /*
     * The floating heart animates only when a tap flips `isSaved`. The slug is
     * tracked beside it so arriving on a different stone profile — which also
     * moves `isSaved` — resets the comparison instead of animating. Comparing
     * during render keeps the class in the same commit as the flip.
     */
    const [previousIsSaved, setPreviousIsSaved] = useState(isSaved);
    const [savedSlug, setSavedSlug] = useState(stoneSlug);
    const [heartAnimation, setHeartAnimation] = useState('');

    if (savedSlug !== stoneSlug) {
        setSavedSlug(stoneSlug);
        setPreviousIsSaved(isSaved);
        setHeartAnimation('');
    } else if (previousIsSaved !== isSaved) {
        setPreviousIsSaved(isSaved);
        setHeartAnimation(isSaved ? 'heart-save' : 'heart-unsave');
    }

    useEffect(() => {
        document.cookie = `${SIDEBAR_COOKIE_NAME}=${String(
            !collapsed,
        )}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
    }, [collapsed]);

    useEffect(() => {
        if (!drawerOpen) {
            return;
        }

        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previousOverflow;
        };
    }, [drawerOpen]);

    const handleLogout = () => {
        router.flushAll();
    };

    const getInitials = useInitials();
    const initials = user ? getInitials(user.name) || 'R' : 'R';

    const crumbs: Array<{ label: string; href?: string }> = [
        { label: 'Regray' },
    ];

    if (effectiveUrl.startsWith('/profile')) {
        /*
         * Everything under /profile hangs off the Profile hub, so the trail is
         * built by hand rather than by segment: the segments would read
         * "account security security" and say nothing about where the page is.
         *
         * Regray / Profile is the floor every one of these shares. Each page
         * then adds its own name, and Security adds the page holding the
         * Security card first, so the trail reads as the route the reader
         * walked rather than as a list of slugs.
         */
        crumbs.push({ label: 'Profile', href: showProfile.url() });

        if (effectiveUrl === editAccountSecurity.url()) {
            crumbs.push({ label: 'Account & Security' });
        } else if (effectiveUrl === profileSchedule.url()) {
            crumbs.push({ label: 'Schedule' });
        } else if (effectiveUrl === '/profile/schedule/book') {
            crumbs.push({ label: 'Schedule', href: profileSchedule.url() });
            crumbs.push({ label: 'Book' });
        } else if (effectiveUrl.startsWith(editSecurity.url())) {
            crumbs.push({
                label: 'Account & Security',
                href: editAccountSecurity.url(),
            });
            crumbs.push({ label: 'Security' });
        }
    } else if (
        effectiveUrl.split('?')[0] === homeIndex.url() ||
        effectiveUrl.startsWith(`${homeIndex.url()}/`)
    ) {
        const stone = (
            page.props.stone as
                | { data: { slug: string; name: string } }
                | undefined
        )?.data;
        const project = (
            page.props.project as
                | {
                      data: {
                          name: string;
                      };
                  }
                | undefined
        )?.data;

        crumbs.push({
            label: 'Home',
            href: homeIndex.url(
                project ? { query: { collection: 'projects' } } : undefined,
            ),
        });

        if (project) {
            crumbs.push({ label: project.name });
        } else if (stone) {
            crumbs.push({ label: stone.name });
        }
    } else {
        url.split('/')
            .filter(Boolean)
            .forEach((segment, index, segments) => {
                const item = allItems.find((entry) => entry.slug === segment);

                if (item) {
                    crumbs.push({ label: item.label, href: item.href });

                    return;
                }

                const previousSegment = segments[index - 1];

                const profile =
                    previousSegment === 'stones' || previousSegment === 'saved'
                        ? (
                              page.props.stone as
                                  | { data: { name: string } }
                                  | undefined
                          )?.data
                        : undefined;

                if (profile) {
                    crumbs.push({ label: profile.name });
                } else {
                    crumbs.push({
                        label:
                            segment.charAt(0).toUpperCase() + segment.slice(1),
                    });
                }
            });
    }

    return (
        <div className="min-h-screen bg-background text-foreground">
            {/* Desktop sidebar (lg and up) */}
            <aside
                className={`fixed inset-y-0 left-0 z-40 hidden flex-col overflow-hidden border-r border-sidebar-border bg-background transition-[width] duration-300 ease-out lg:flex ${
                    collapsed ? 'w-[4.5rem]' : 'w-64'
                }`}
            >
                <div
                    className={`flex h-16 shrink-0 items-center border-b border-sidebar-border transition-all duration-300 ${
                        collapsed ? 'justify-center px-0' : 'px-6'
                    }`}
                >
                    <Logo compact={collapsed} />
                </div>

                <SidebarNav
                    collapsed={collapsed}
                    pathname={activePath}
                    savedCount={savedCount}
                />

                {/* Collapse / expand toggle (desktop only) */}
                <div
                    className={`flex shrink-0 items-center border-t border-sidebar-border p-3 transition-all duration-300 ${
                        collapsed ? 'justify-center px-0' : 'justify-start'
                    }`}
                >
                    <button
                        type="button"
                        onClick={() => setCollapsed((value) => !value)}
                        aria-label={
                            collapsed ? 'Expand sidebar' : 'Collapse sidebar'
                        }
                        aria-expanded={!collapsed}
                        className="grid h-10 w-10 place-items-center rounded-md border border-sidebar-border text-foreground transition-all duration-300 hover:scale-[1.05] hover:opacity-80"
                    >
                        {collapsed ? (
                            <PanelLeftOpen className="size-4" aria-hidden />
                        ) : (
                            <PanelLeftClose className="size-4" aria-hidden />
                        )}
                    </button>
                </div>
            </aside>

            {/* Main column */}
            <div
                className={`flex min-h-screen flex-col transition-[padding] duration-300 ease-out ${
                    collapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-64'
                }`}
            >
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-sidebar-border bg-background px-4 sm:px-5 lg:h-16 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                        <Logo className="shrink-0 lg:hidden" />
                        <span
                            className="hidden h-6 w-px shrink-0 bg-sidebar-border md:block lg:hidden"
                            aria-hidden
                        />
                        <nav
                            aria-label="Breadcrumb"
                            className="hidden min-w-0 items-center gap-2 overflow-x-auto text-xs md:flex lg:text-sm"
                        >
                            {crumbs.map((crumb, index) => {
                                const isLast = index === crumbs.length - 1;

                                return (
                                    <span
                                        key={`${crumb.label}-${index}`}
                                        className="flex min-w-0 items-center gap-2"
                                    >
                                        {index > 0 && (
                                            <span className="shrink-0 text-secondary-foreground">
                                                /
                                            </span>
                                        )}
                                        {index > 0 && !isLast && crumb.href ? (
                                            <Link
                                                href={crumb.href}
                                                className="shrink-0 text-secondary-foreground transition-opacity hover:opacity-70"
                                            >
                                                {crumb.label}
                                            </Link>
                                        ) : (
                                            <span
                                                className={
                                                    isLast
                                                        ? 'truncate font-bold text-foreground'
                                                        : 'shrink-0 text-secondary-foreground'
                                                }
                                            >
                                                {crumb.label}
                                            </span>
                                        )}
                                    </span>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    type="button"
                                    className="group flex h-11 w-11 items-center justify-center rounded-md transition-opacity hover:opacity-80 sm:w-auto sm:gap-1.5 sm:px-3 sm:py-2"
                                    /*
                                     * The initials are the button's visible
                                     * text, so its name carries them too: a
                                     * reader using speech input says what they
                                     * see.
                                     */
                                    aria-label={`Account, ${initials}`}
                                >
                                    <Avatar className="h-9 w-9 border border-border sm:size-9">
                                        <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">
                                            {initials}
                                        </AvatarFallback>
                                    </Avatar>
                                    <ChevronDown
                                        className="hidden size-4 text-secondary-foreground transition-transform duration-300 ease-in-out group-data-[state=open]:rotate-180 sm:block"
                                        aria-hidden
                                    />
                                </button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuItem>
                                    <HelpCircle className="mr-2" />
                                    Help
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={showProfile()}
                                        as="button"
                                        className="block w-full cursor-pointer"
                                    >
                                        <CircleUserRound className="mr-2" />
                                        Profile
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => {
                                        toast.info(
                                            'Switch account is coming soon.',
                                        );
                                    }}
                                >
                                    <ArrowLeftRight className="mr-2" />
                                    Switch account
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={logout()}
                                        as="button"
                                        onClick={handleLogout}
                                        data-test="logout-button"
                                        className="block w-full cursor-pointer"
                                    >
                                        <LogOut className="mr-2" />
                                        Log out
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <button
                            type="button"
                            onClick={() => setDrawerOpen(true)}
                            className="grid h-11 w-11 place-items-center rounded-md transition-opacity hover:opacity-85 lg:hidden"
                            aria-label="Open navigation"
                            aria-expanded={drawerOpen}
                        >
                            <Menu className="size-5" />
                        </button>
                    </div>
                </header>

                <main className="flex-1 px-4 py-6 sm:px-5 sm:py-8 lg:px-8">
                    <div
                        key={url}
                        className="page-enter motion-reduce:animate-none"
                    >
                        {children}
                    </div>
                </main>
            </div>

            {/* Drawer backdrop */}
            <div
                className={`fixed inset-0 z-40 bg-black/60 transition-opacity duration-300 lg:hidden ${
                    drawerOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
                onClick={() => setDrawerOpen(false)}
                aria-hidden
            />

            {/* Drawer panel */}
            <aside
                role="dialog"
                aria-modal="true"
                aria-label="Navigation"
                inert={!drawerOpen}
                className={`fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border bg-background transition-transform duration-300 lg:hidden ${
                    drawerOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center justify-between border-b border-border px-6">
                    <Logo />
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(false)}
                        className="grid size-9 place-items-center rounded-md transition-opacity hover:opacity-85"
                        aria-label="Close navigation"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <SidebarNav
                    collapsed={false}
                    pathname={activePath}
                    savedCount={savedCount}
                    onNavigate={() => setDrawerOpen(false)}
                />
            </aside>

            {isStoneProfilePage && canSave && stoneSlug && (
                <div className="fixed right-4 bottom-4 z-40 flex flex-col items-end gap-2 pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] sm:right-5 sm:bottom-5">
                    <Button
                        type="button"
                        variant={isSaved ? 'default' : 'outline'}
                        size="icon"
                        className={`h-11 w-11 cursor-pointer shadow-lg active:scale-90 ${
                            isSaved ? '' : 'bg-background'
                        }`}
                        aria-pressed={isSaved ? 'true' : 'false'}
                        aria-label={
                            isSaved ? 'Remove from saved' : 'Save to collection'
                        }
                        onClick={() => toggleSave(stoneSlug)}
                    >
                        <Heart
                            key={heartAnimation}
                            className={`size-5 ${
                                isSaved
                                    ? 'fill-primary-foreground text-primary-foreground'
                                    : 'text-foreground'
                            } ${heartAnimation}`}
                            aria-hidden
                        />
                    </Button>
                </div>
            )}
        </div>
    );
};

export default WorkspaceLayout;
