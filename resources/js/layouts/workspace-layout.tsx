import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Calendar,
    ChevronDown,
    CircleUserRound,
    FileText,
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
    Settings,
    ShieldCheck,
    Users,
    Wrench,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import Logo from '@/components/logo';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { index as accountsIndex } from '@/routes/accounts';
import { index as homeIndex } from '@/routes/home';
import { logout } from '@/routes';
import { index as inquiryIndex } from '@/routes/inquiry';
import { index as messageIndex } from '@/routes/message';
import { index as overviewIndex } from '@/routes/overview';
import { index as stonesIndex } from '@/routes/stones';
import { edit as editProfile } from '@/routes/profile';
import { index as quotationIndex } from '@/routes/quotation';
import { index as salesIndex } from '@/routes/sales';
import { index as savedIndex } from '@/routes/saved';
import { index as scheduleIndex } from '@/routes/schedule';
import { edit as editSecurity } from '@/routes/security';
import { index as servicesIndex } from '@/routes/services';

type NavItem = {
    slug: string;
    label: string;
    href: string;
    icon: LucideIcon;
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
            },
            {
                slug: 'overview',
                label: 'Overview',
                href: overviewIndex.url(),
                icon: LayoutDashboard,
            },
            {
                slug: 'saved',
                label: 'Saved',
                href: savedIndex.url(),
                icon: Heart,
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
            },
            {
                slug: 'quotation',
                label: 'Quotation',
                href: quotationIndex.url(),
                icon: Handshake,
            },
            {
                slug: 'inquiry',
                label: 'Inquiry',
                href: inquiryIndex.url(),
                icon: FileText,
            },
            {
                slug: 'schedule',
                label: 'Schedule',
                href: scheduleIndex.url(),
                icon: Calendar,
            },
            {
                slug: 'message',
                label: 'Message',
                href: messageIndex.url(),
                icon: MessageCircle,
            },
            {
                slug: 'stones',
                label: 'Stones',
                href: stonesIndex.url(),
                icon: Package,
            },
            {
                slug: 'services',
                label: 'Services',
                href: servicesIndex.url(),
                icon: Wrench,
            },
            {
                slug: 'accounts',
                label: 'Accounts',
                href: accountsIndex.url(),
                icon: Users,
            },
        ],
    },
    {
        label: 'Settings',
        items: [
            {
                slug: 'settings',
                label: 'Profile',
                href: editProfile.url(),
                icon: CircleUserRound,
                matchPrefix: '/settings/profile',
            },
            {
                slug: 'security',
                label: 'Security',
                href: editSecurity.url(),
                icon: ShieldCheck,
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
    `relative flex shrink-0 flex-row items-center py-2.5 text-sm transition-opacity hover:opacity-85 ${
        collapsed ? 'justify-center gap-0 px-0' : 'gap-3 px-4'
    } ${
        isActive
            ? 'bg-primary font-bold text-primary-foreground'
            : 'text-secondary-foreground'
    }`;

const SidebarNav = ({
    collapsed,
    pathname,
    onNavigate,
}: {
    collapsed: boolean;
    pathname: string;
    onNavigate?: () => void;
}) => (
    <nav
        className={`flex-1 overflow-y-auto py-5 ${
            collapsed ? 'space-y-6' : 'space-y-6 px-3'
        }`}
    >
        {navGroups.map((group) => (
            <div key={group.label}>
                <p
                    className={`overflow-hidden pb-2 text-[0.68rem] font-bold tracking-[0.16em] whitespace-nowrap text-secondary-foreground uppercase transition-all duration-300 ${
                        collapsed
                            ? 'max-h-0 opacity-0'
                            : 'max-h-8 px-3 opacity-100'
                    }`}
                >
                    {group.label}
                </p>
                <ul className="space-y-1">
                    {group.items.map((item) => {
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
                                        </Link>
                                    </TooltipTrigger>
                                    {collapsed && (
                                        <TooltipContent
                                            side="right"
                                            className="rounded-none"
                                        >
                                            {item.label}
                                        </TooltipContent>
                                    )}
                                </Tooltip>
                            </li>
                        );
                    })}
                </ul>
            </div>
        ))}
    </nav>
);

const WorkspaceLayout = ({ children }: { children: ReactNode }) => {
    const page = usePage();
    const url = page.url.split('?')[0];
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [collapsed, setCollapsed] = useState<boolean>(
        () => !page.props.sidebarOpen,
    );

    const user = page.props.auth.user;

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

    url.split('/')
        .filter(Boolean)
        .forEach((segment) => {
            const item = allItems.find((entry) => entry.slug === segment);

            if (item) {
                crumbs.push({ label: item.label, href: item.href });
            } else {
                crumbs.push({
                    label: segment.charAt(0).toUpperCase() + segment.slice(1),
                });
            }
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
                    <Logo compact={collapsed} />
                </div>

                <SidebarNav collapsed={collapsed} pathname={url} />

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
                        className="grid size-9 place-items-center border border-sidebar-border text-foreground transition-all duration-300 hover:scale-[1.05] hover:opacity-80"
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
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-sidebar-border bg-background px-5 lg:px-8">
                    <div className="flex min-w-0 items-center gap-4">
                        <Logo className="shrink-0 lg:hidden" />
                        <span
                            className="hidden h-6 w-px shrink-0 bg-sidebar-border sm:block lg:hidden"
                            aria-hidden
                        />
                        <nav
                            aria-label="Breadcrumb"
                            className="hidden min-w-0 items-center gap-2 overflow-x-auto text-sm sm:flex"
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
                                    className="group flex items-center gap-1.5 transition-opacity hover:opacity-80"
                                    aria-label="Account"
                                >
                                    <Avatar className="size-9 border border-border">
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

                            <DropdownMenuContent
                                align="end"
                                className="w-48 rounded-none"
                            >
                                <DropdownMenuItem>
                                    <HelpCircle className="mr-2" />
                                    Help
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={editProfile()}
                                        as="button"
                                        className="block w-full cursor-pointer"
                                    >
                                        <Settings className="mr-2" />
                                        Settings
                                    </Link>
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
                            className="grid size-10 place-items-center transition-opacity hover:opacity-85 lg:hidden"
                            aria-label="Open navigation"
                            aria-expanded={drawerOpen}
                        >
                            <Menu className="size-5" />
                        </button>
                    </div>
                </header>

                <main className="flex-1 px-5 py-8 lg:px-8">
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
                        className="grid size-9 place-items-center transition-opacity hover:opacity-85"
                        aria-label="Close navigation"
                    >
                        <X className="size-4" />
                    </button>
                </div>
                <SidebarNav
                    collapsed={false}
                    pathname={url}
                    onNavigate={() => setDrawerOpen(false)}
                />
            </aside>
        </div>
    );
};

export default WorkspaceLayout;
