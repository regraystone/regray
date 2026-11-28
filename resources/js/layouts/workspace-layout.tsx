import { backTarget } from '@/lib/back-target';
import { messageUsPlacement } from '@/lib/message-us';
import { catalogueCrumbs, type Crumb } from '@/lib/catalogue-breadcrumbs';
import { profileCrumbs } from '@/lib/profile-breadcrumbs';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bookmark,
    Calendar,
    ChevronDown,
    ChevronLeft,
    CircleUserRound,
    FileText,
    Folder,
    Home,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageCircleMore,
    Package,
    Target,
    User,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Store,
    Users,
    Wrench,
} from 'lucide-react';
import { createLucideIcon, type LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import Logo from '@/components/logo';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import LogoutDialog from '@/components/logout-dialog';
import { Button } from '@/components/ui/button';
import NotificationBell from '@/components/notification-bell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { useSavedFurniture, useSavedStones } from '@/hooks/use-saved-stones';
import { getEcho, listenTo, onReconnect } from '@/lib/echo';
import { onUnreadChanged } from '@/lib/unread-badge';
import type { Realtime } from '@/types/message';
import { index as accountsIndex } from '@/routes/accounts';
import { index as scheduleIndex } from '@/routes/schedule';
import {
    index as dashboardBrandsIndex,
    show as dashboardBrandShow,
} from '@/routes/dashboard/brands';
import { dashboard } from '@/routes';
import { index as homeIndex } from '@/routes/home';
import { index as leadsIndex } from '@/routes/leads';
import {
    index as inquiryIndex,
    inquire as inquiryInquire,
} from '@/routes/inquiry';
import { index as inboxIndex } from '@/routes/inbox';
import { index as overviewIndex } from '@/routes/overview';
import { index as productListIndex } from '@/routes/dashboard/products/lists';
import { index as productsIndex } from '@/routes/products';
import { index as showcasesIndex } from '@/routes/showcases';
import {
    schedule as profileSchedule,
    edit as editAccountSecurity,
    settings as profileSettings,
    show as showProfile,
} from '@/routes/profile';
import { index as profileAddresses } from '@/routes/profile/addresses';
import { book as profileScheduleBook } from '@/routes/profile/schedule';
import { index as quoteIndex, inquire as quoteInquire } from '@/routes/quote';
import { index as salesIndex } from '@/routes/sales';
import { index as savedIndex } from '@/routes/saved';
import { edit as editSecurity } from '@/routes/security';
import { index as serviceListIndex } from '@/routes/dashboard/services/lists';
import { index as servicesIndex } from '@/routes/services';

/**
 * The permissions the app knows about, mirroring App\Enums\Permission.
 *
 * The server shares the signed-in user's granted names as `auth.permissions`,
 * so the only thing that has to agree between the two sides is the spelling.
 * Keeping the union here means the sidebar's `permission` field, and
 * `usePermissions().can()`, are checked against this list rather than an
 * untyped string. It lists every case, the action permissions (create,
 * update, archive, ...) included; PermissionMirrorTest keeps the two in step.
 *
 * `profileScheduleView` and `homeShowcasesView` are listed but no nav item names
 * them: each is the permission for a page reached from another page rather
 * than from the sidebar. `profileScheduleView` is the schedule as reached from the
 * Profile page, and the button for it is drawn there rather than in the
 * sidebar — see the Profile hub page. `homeShowcasesView` names the showcase
 * profile and the stone profile nested under it, both read from Home, where
 * the showcase cards are drawn by the page that already holds `homeView`.
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
    brandsView: 'brands.view',
    productsView: 'products.view',
    servicesView: 'services.view',
    accountsView: 'accounts.view',
    showcasesView: 'showcases.view',
    profileView: 'profile.view',
    leadsView: 'leads.view',
    profileScheduleView: 'profile-schedule.view',
    profileAddressesView: 'profile-addresses.view',
    homeShowcasesView: 'home-showcases.view',
    inquiriesCreate: 'inquiries.create',
    scheduleCreate: 'schedule.create',
    scheduleUpdate: 'schedule.update',
    scheduleCancel: 'schedule.cancel',
    inquiriesCreatePlatform: 'inquiries.create.platform',
    scheduleAssign: 'schedule.assign',
    scheduleConfirm: 'schedule.confirm',
    scheduleAttend: 'schedule.attend',
    conversationsCreate: 'conversations.create',
    supportClaim: 'support.claim',
    leadsUpdate: 'leads.update',
    homeStoneView: 'home-stone.view',
    savedStoneView: 'saved-stone.view',
    savedShowcasesView: 'saved-showcases.view',
    inquiriesEdit: 'inquiries.edit',
    inquiriesStatusSales: 'inquiries.status.sales',
    inquiriesStatusPayment: 'inquiries.status.payment',
    inquiriesStatusProcess: 'inquiries.status.process',
    accountsCreate: 'accounts.create',
    accountsUpdate: 'accounts.update',
    accountsArchive: 'accounts.archive',
    brandsCreate: 'brands.create',
    brandsUpdate: 'brands.update',
    brandsArchive: 'brands.archive',
    showcasesCreate: 'showcases.create',
    showcasesUpdate: 'showcases.update',
    showcasesArchive: 'showcases.archive',
    productsCreate: 'products.create',
    productsUpdate: 'products.update',
    productsArchive: 'products.archive',
    servicesCreate: 'services.create',
    servicesUpdate: 'services.update',
    servicesArchive: 'services.archive',
} as const;

export type Permission = (typeof permissions)[keyof typeof permissions];

/** Quote's icon in the menu: a page with two lines of writing, as Sir Mark's design draws it. */
const QuotePage = createLucideIcon('quote-page', [
    [
        'rect',
        { x: '5', y: '3.5', width: '14', height: '17', rx: '3.5', key: 'page' },
    ],
    ['path', { d: 'M9 10.5h6', key: 'first-line' }],
    ['path', { d: 'M9 14.5h4', key: 'second-line' }],
]);

type NavItem = {
    slug: string;
    label: string;
    href: string;
    icon: LucideIcon;
    permission: Permission;
    matchPrefix?: string;
    /**
     * The pages under it: it opens as an accordion of these in the full
     * sidebar, and links to the first when the sidebar is icons only.
     */
    children?: Array<{ slug: string; label: string; href: string }>;
};

/** Products' pages: every product, then the lists products are built from. */
const productPages = [
    { slug: 'all-products', label: 'All products', href: productsIndex.url() },
    ...(
        [
            ['stone-types', 'Stone types'],
            ['sizes', 'Sizes'],
            ['thicknesses', 'Thicknesses'],
            ['finishes', 'Finishes'],
            ['edge-profiles', 'Edge profiles'],
            ['furniture-types', 'Furniture types'],
            ['applications', 'Applications'],
            ['care-tips', 'Care tips'],
        ] as const
    ).map(([slug, label]) => ({
        slug,
        label,
        href: productListIndex.url(slug),
    })),
];

/** Services' pages: every service, then the lists services are built from. */
const servicePages = [
    { slug: 'all-services', label: 'All services', href: servicesIndex.url() },
    ...(
        [
            ['service-types', 'Service types'],
            ['questions', 'Questions'],
            ['choices', 'Choices'],
        ] as const
    ).map(([slug, label]) => ({
        slug,
        label,
        href: serviceListIndex.url(slug),
    })),
];

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
                icon: Bookmark,
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
                icon: QuotePage,
                permission: permissions.quotationsView,
            },
            {
                slug: 'leads',
                label: 'Leads',
                href: leadsIndex.url(),
                icon: Target,
                permission: permissions.leadsView,
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
                slug: 'inbox',
                label: 'Inbox',
                href: inboxIndex.url(),
                icon: MessageCircleMore,
                permission: permissions.messagesView,
            },
            {
                slug: 'brands',
                label: 'Brands',
                href: dashboardBrandsIndex.url(),
                icon: Store,
                permission: permissions.brandsView,
            },
            {
                slug: 'products',
                label: 'Products',
                href: productsIndex.url(),
                icon: Package,
                permission: permissions.productsView,
                children: productPages,
            },
            {
                slug: 'services',
                label: 'Services',
                href: servicesIndex.url(),
                icon: Wrench,
                permission: permissions.servicesView,
                children: servicePages,
            },
            {
                slug: 'showcases',
                label: 'Showcase',
                href: showcasesIndex.url(),
                icon: Folder,
                permission: permissions.showcasesView,
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
                icon: User,
                permission: permissions.profileView,
                matchPrefix: showProfile.url(),
            },
        ],
    },
];

const allItems = navGroups.flatMap((group) =>
    group.items.flatMap((item) => [
        item,
        ...(item.children ?? []).filter((child) => child.href !== item.href),
    ]),
);

/**
 * A sidebar item with pages under it (Products, Services): its row opens and closes
 * the list of them, open by itself while one of them is the page shown, with
 * that one lit.
 */
const NavAccordion = ({
    item,
    pathname,
    onNavigate,
}: {
    item: NavItem & { children: NonNullable<NavItem['children']> };
    pathname: string;
    onNavigate?: () => void;
}) => {
    const inSection =
        pathname === item.href || pathname.startsWith(`${item.href}/`);
    const [open, setOpen] = useState(inSection);

    // Going to one of its pages from elsewhere opens it.
    useEffect(() => {
        if (inSection) {
            setOpen(true);
        }
    }, [inSection]);

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger
                className={`${navLinkClass({ isActive: false, collapsed: false })} w-full ${
                    inSection ? 'font-bold text-foreground' : ''
                }`}
            >
                <item.icon className="size-4 shrink-0" aria-hidden />
                <span className="overflow-hidden whitespace-nowrap uppercase">
                    {item.label}
                </span>
                <ChevronDown
                    aria-hidden
                    className={`ml-auto size-4 shrink-0 transition-transform ${
                        open ? 'rotate-180' : ''
                    }`}
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul className="space-y-0.5 py-1" aria-label={item.label}>
                    {item.children.map((child) => {
                        const isActive = pathname === child.href;

                        return (
                            <li key={child.slug}>
                                <Link
                                    href={child.href}
                                    onClick={onNavigate}
                                    aria-current={isActive ? 'page' : undefined}
                                    className={`relative flex items-center py-2 pr-7 pl-14 text-sm whitespace-nowrap ${
                                        isActive
                                            ? 'bg-primary font-bold text-primary-foreground'
                                            : 'text-secondary-foreground hover:text-foreground'
                                    }`}
                                >
                                    {isActive && (
                                        <span
                                            className="absolute inset-y-0 left-0 w-1 bg-primary-foreground"
                                            aria-hidden
                                        />
                                    )}
                                    {child.label}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
};

const SIDEBAR_COOKIE_NAME = 'sidebar_state';
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;

const navLinkClass = ({
    isActive,
    collapsed,
}: {
    isActive: boolean;
    collapsed: boolean;
}) =>
    `relative flex shrink-0 flex-row items-center overflow-hidden py-2.5 text-sm ${
        collapsed ? 'justify-center gap-0 px-0' : 'gap-3 px-7'
    } ${
        isActive
            ? 'bg-primary font-bold text-primary-foreground'
            : 'text-secondary-foreground'
    }`;

const SidebarNav = ({
    collapsed,
    pathname,
    savedCount,
    unreadCount,
    onNavigate,
}: {
    collapsed: boolean;
    pathname: string;
    savedCount: number;
    /** Unread messages across the user's conversations. */
    unreadCount: number;
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
                collapsed ? 'space-y-6' : 'space-y-1'
            }`}
        >
            <ul className="space-y-1">
                {visibleGroups.flatMap((group) =>
                    group.items.map((item) => {
                        const isActive = item.matchPrefix
                            ? pathname.startsWith(item.matchPrefix)
                            : pathname === item.href ||
                              pathname.startsWith(`${item.href}/`);

                        if (item.children && !collapsed) {
                            return (
                                <li key={item.slug}>
                                    <NavAccordion
                                        item={{
                                            ...item,
                                            children: item.children,
                                        }}
                                        pathname={pathname}
                                        onNavigate={onNavigate}
                                    />
                                </li>
                            );
                        }

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
                                                className={`overflow-hidden whitespace-nowrap uppercase transition-all duration-300 ${
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
                                            {item.slug === 'inbox' &&
                                                unreadCount > 0 &&
                                                !collapsed && (
                                                    <span
                                                        className="ml-auto grid min-w-5 place-items-center rounded-full bg-foreground/10 px-1.5 py-0.5 text-[0.65rem] font-bold"
                                                        data-testid="sidebar-unread-badge"
                                                    >
                                                        {unreadCount > 99
                                                            ? '99+'
                                                            : unreadCount}
                                                        <span className="sr-only">
                                                            {' '}
                                                            unread
                                                        </span>
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
    const isInboxPage = page.component === 'inbox';
    const effectiveUrl = isConfirmPassword ? editSecurity.url() : url;

    const isStoneProfilePage = page.component === 'stone-profile';

    /*
     * The stepper has no sidebar item of its own; it is nested under the list
     * the user reads their inquiries on, so that list is what stays lit.
     */
    const isInquire =
        url === quoteInquire.url() || url === inquiryInquire.url();

    // A brand and its products are reached from Home.
    const isHomeChildPage = url.startsWith('/dashboard/brands/');

    const activePath = isInquire
        ? url.slice(0, url.lastIndexOf('/'))
        : isHomeChildPage
          ? homeIndex.url()
          : isStoneProfilePage && prevUrlRef.current
            ? prevUrlRef.current
            : effectiveUrl;

    const user = page.props.auth.user;
    const savedStones = useSavedStones();
    const savedFurniture = useSavedFurniture();
    // The Saved badge counts both kinds. The stone and furniture profiles
    // draw their own Save beside their Ask a quote.
    const savedCount = savedStones.savedIds.size + savedFurniture.savedIds.size;
    const unreadCount = page.props.unreadMessageCount ?? 0;
    const { can } = usePermissions();
    const canMessage = can(permissions.messagesView);
    const isInSupportPool = can(permissions.supportClaim);
    const realtime = page.props.realtime as Realtime | null | undefined;
    const userId = user?.id;

    /*
     * Keep the Inbox badge current wherever the reader is: the inbox channel
     * (and, for the support pool, the pool's) says a conversation's row
     * changed, and this tab says when it has just read messages. Either way
     * only the count is asked for again, in the background, so it never cuts
     * a visit short. A burst of events is one reload.
     *
     * The channels are not left on the way out: the Inbox page listens on
     * the same ones, and leaving here would deafen it too.
     */
    useEffect(() => {
        if (!canMessage || userId === undefined) {
            return;
        }

        let timer: number | null = null;
        const refresh = () => {
            if (timer !== null) {
                window.clearTimeout(timer);
            }

            timer = window.setTimeout(() => {
                timer = null;
                router.reload({ only: ['unreadMessageCount'], async: true });
            }, 400);
        };

        const stopRead = onUnreadChanged(refresh);
        const echo = getEcho(realtime ?? undefined);
        const stopInbox =
            echo === null
                ? null
                : listenTo(echo.private(`user.${userId}`), {
                      '.conversation.activity': refresh,
                      '.conversation.added': refresh,
                  });
        const stopPool =
            echo !== null && isInSupportPool
                ? listenTo(echo.private('support.pool'), {
                      '.conversation.activity': refresh,
                      '.support.claimed': refresh,
                  })
                : null;
        const stopReconnect = echo === null ? null : onReconnect(echo, refresh);

        return () => {
            stopRead();
            stopInbox?.();
            stopPool?.();
            stopReconnect?.();

            if (timer !== null) {
                window.clearTimeout(timer);
            }
        };
    }, [canMessage, isInSupportPool, realtime, userId]);

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

        // No close button: the overlay, a link, or Escape closes the drawer.
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setDrawerOpen(false);
            }
        };

        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('keydown', closeOnEscape);
            document.body.style.overflow = previousOverflow;
        };
    }, [drawerOpen]);

    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const getInitials = useInitials();
    const initials = user ? getInitials(user.name) || 'R' : 'R';

    const catalogueTrail = isHomeChildPage
        ? catalogueCrumbs({
              component: page.component,
              props: page.props,
              homeHref: homeIndex.url(),
              brandHref: (brand) => dashboardBrandShow.url({ brand }),
          })
        : null;

    /*
     * Everything under the Profile hub hangs off it, so its trail is built by
     * name rather than by segment (lib/profile-breadcrumbs.ts): the segments
     * would read "account security security" and say nothing about where the
     * page is.
     */
    const profileTrail = profileCrumbs(effectiveUrl, {
        profile: showProfile.url(),
        settings: profileSettings.url(),
        accountSecurity: editAccountSecurity.url(),
        security: editSecurity.url(),
        schedule: profileSchedule.url(),
        scheduleBook: profileScheduleBook.url(),
        addresses: profileAddresses.url(),
    });

    const crumbs: Crumb[] = catalogueTrail ??
        profileTrail ?? [{ label: 'Regray' }];

    if (profileTrail) {
        // The trail above is the whole of it.
    } else if (catalogueTrail) {
        // The trail above is the whole of it.
    } else if (
        effectiveUrl.split('?')[0] === savedIndex.url() ||
        effectiveUrl.startsWith(`${savedIndex.url()}/`)
    ) {
        crumbs.push({ label: 'Dashboard' });
        crumbs.push({ label: 'Saved', href: savedIndex.url() });

        const stone = (
            page.props.stone as { data: { name: string } } | undefined
        )?.data;
        const furniture = (
            page.props.furniture as { data: { name: string } } | undefined
        )?.data;
        const item = stone ?? furniture;

        if (item) {
            crumbs.push({ label: item.name });
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
        const showcase = (
            page.props.showcase as
                | {
                      data: {
                          name: string;
                      };
                  }
                | undefined
        )?.data;

        crumbs.push({ label: 'Dashboard' });
        crumbs.push({ label: 'Home', href: homeIndex.url() });

        if (showcase) {
            crumbs.push({ label: showcase.name });
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

    // A page opened from somewhere it should return to (Addresses from Inquire) names it as `returnTo`.
    const returnTo = (page.props as { returnTo?: unknown }).returnTo;
    const backHref =
        typeof returnTo === 'string' && returnTo !== ''
            ? returnTo
            : backTarget({
                  component: page.component,
                  url: effectiveUrl,
                  crumbs,
                  landingUrls: [homeIndex.url(), overviewIndex.url()],
                  landing: dashboard.url(),
              });

    const messageUsAt = messageUsPlacement({
        component: page.component,
        roles: page.props.auth.roles ?? [],
        canMessage,
    });

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
                    <Logo compact={collapsed} className="text-sm" />
                </div>

                <SidebarNav
                    collapsed={collapsed}
                    pathname={activePath}
                    savedCount={savedCount}
                    unreadCount={unreadCount}
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
                } ${isInboxPage ? 'max-md:min-h-0' : ''}`}
            >
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-sidebar-border bg-background px-4 sm:px-5 lg:h-16 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                        {/* On the narrowest phones only REGRAY fits beside the account, bell and menu. */}
                        <Logo className="shrink-0 text-[0.6875rem] max-[339px]:text-sm min-[400px]:text-sm lg:hidden max-[339px]:[&_[data-wordmark-name]]:hidden" />
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
                                                className="shrink-0 text-secondary-foreground uppercase transition-opacity hover:opacity-70"
                                            >
                                                {crumb.label}
                                            </Link>
                                        ) : (
                                            <span
                                                className={
                                                    isLast
                                                        ? 'truncate font-bold text-foreground uppercase'
                                                        : 'shrink-0 text-secondary-foreground uppercase'
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
                                        {page.props.auth.avatar ? (
                                            <AvatarImage
                                                src={page.props.auth.avatar}
                                                alt=""
                                                className="object-cover"
                                            />
                                        ) : null}
                                        <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">
                                            {initials}
                                        </AvatarFallback>
                                    </Avatar>
                                    <ChevronDown
                                        className="size-4 text-secondary-foreground transition-transform duration-300 ease-in-out group-data-[state=open]:rotate-180"
                                        aria-hidden
                                    />
                                </button>
                            </DropdownMenuTrigger>

                            {/* Centred under the avatar, as the bell's list is; kept 16px inside a narrow screen. */}
                            <DropdownMenuContent
                                align="center"
                                collisionPadding={16}
                                className="w-48"
                            >
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
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={profileSettings()}
                                        as="button"
                                        className="block w-full cursor-pointer"
                                    >
                                        <Settings className="mr-2" />
                                        Settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => setIsLoggingOut(true)}
                                    data-test="logout-button"
                                    className="cursor-pointer"
                                >
                                    <LogOut className="mr-2" />
                                    Log out
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        {userId !== undefined ? (
                            <NotificationBell
                                unreadCount={
                                    page.props.unreadNotificationCount ?? 0
                                }
                                realtime={realtime}
                                userId={userId}
                            />
                        ) : null}

                        <LogoutDialog
                            open={isLoggingOut}
                            onOpenChange={setIsLoggingOut}
                        />

                        <button
                            type="button"
                            onClick={() => setDrawerOpen(true)}
                            className="grid h-11 w-11 place-items-center rounded-md transition-opacity hover:opacity-85 lg:hidden"
                            aria-label="Open navigation"
                            aria-expanded={drawerOpen}
                        >
                            {/* The three-line menu, as Sir Mark asked for it back. */}
                            <Menu aria-hidden className="size-6" />
                        </button>
                    </div>
                </header>

                {/*
                 * Messages fill the screen under the header on phones, edge to
                 * edge; from md up they sit in the page like everything else.
                 */}
                <main
                    className={`flex-1 ${
                        isInboxPage
                            ? 'md:px-5 md:py-8 lg:px-8'
                            : 'px-4 py-6 sm:px-5 sm:py-8 lg:px-8'
                    }`}
                >
                    <div
                        key={url}
                        className="page-enter motion-reduce:animate-none"
                    >
                        {/* Phones and tablets have no breadcrumbs, so each page but a landing one opens with Back. */}
                        {backHref ? (
                            <Link
                                href={backHref}
                                className="-mt-2 mb-4 inline-flex min-h-11 items-center gap-1 pr-3 text-[0.8125rem] font-semibold tracking-[0.08em] uppercase transition-opacity hover:opacity-70 sm:-mt-4 lg:hidden"
                                data-testid="mobile-back"
                            >
                                <ChevronLeft
                                    aria-hidden
                                    strokeWidth={1.5}
                                    className="size-5"
                                />
                                Back
                            </Link>
                        ) : null}
                        {children}
                    </div>
                </main>
            </div>

            {/* A client's way to the team from any page but Inbox, above any bar the page pins to the bottom. */}
            {messageUsAt !== null ? (
                <Button
                    asChild
                    size="lg"
                    className={`fixed right-[max(1rem,env(safe-area-inset-right,0px))] z-30 shadow-lg ${messageUsAt}`}
                >
                    <Link href={inboxIndex.url()} data-testid="message-us">
                        Message us
                    </Link>
                </Button>
            ) : null}

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
                className={`fixed inset-y-0 left-0 z-50 flex w-72 flex-col overflow-hidden border-r border-border bg-background transition-transform duration-300 lg:hidden ${
                    drawerOpen ? 'translate-x-0' : '-translate-x-full'
                }`}
            >
                <div className="flex h-16 items-center border-b border-border px-6">
                    <Logo className="text-sm" />
                </div>
                <SidebarNav
                    collapsed={false}
                    pathname={activePath}
                    savedCount={savedCount}
                    unreadCount={unreadCount}
                    onNavigate={() => setDrawerOpen(false)}
                />
            </aside>
        </div>
    );
};

export default WorkspaceLayout;
