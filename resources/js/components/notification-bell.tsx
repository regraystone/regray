import { router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { getEcho, listenTo, onReconnect } from '@/lib/echo';
import {
    type AppNotification,
    type NotificationCreatedEvent,
    fetchNotifications,
    markAllNotificationsRead,
    openNotification,
} from '@/lib/notification-api';
import { badgeLabel, relativeTime } from '@/lib/notification-format';
import type { Realtime } from '@/types/message';

export const UNAVAILABLE = 'This item is no longer available';

/** Lines shaped like notifications (a dot, the line, its time) while a page loads. */
const NotificationSkeleton = ({ rows }: { rows: number }) => (
    <div role="status" aria-label="Loading notifications">
        {Array.from({ length: rows }, (_, index) => (
            <div
                key={index}
                className="flex items-start gap-2.5 border-b border-border px-3 py-2.5 last:border-b-0"
                aria-hidden
            >
                <Skeleton className="mt-1.5 size-2 shrink-0 rounded-full" />
                <div className="min-w-0 flex-1 space-y-1.5">
                    <Skeleton
                        className={`h-3.5 ${index % 2 === 0 ? 'w-4/5' : 'w-3/5'}`}
                    />
                    <Skeleton className="h-3 w-16" />
                </div>
            </div>
        ))}
    </div>
);

/**
 * The bell in the workspace header, before the account menu.
 *
 * The badge counts unread notifications ("9+" above nine) and is hidden at
 * none. Opening the list does not read anything: only opening a notification,
 * or "Mark all as read", does. The list is newest first, ten at a time,
 * loading more as it scrolls. A new notification arrives over the reader's
 * own channel, so the badge moves without a reload.
 *
 * Opening a notification asks the server where it leads, against what the
 * reader may open now: one they no longer can stays in the list and says so
 * instead of navigating.
 */
const NotificationBell = ({
    unreadCount: initialUnread,
    realtime,
    userId,
}: {
    unreadCount: number;
    realtime: Realtime | null | undefined;
    userId: number;
}) => {
    const [unread, setUnread] = useState(initialUnread);
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<AppNotification[]>([]);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(false);
    // The first page, as the list opens: skeleton lines stand in for the whole list.
    const [loadingFirst, setLoadingFirst] = useState(false);
    const [failed, setFailed] = useState(false);
    const [unavailable, setUnavailable] = useState<ReadonlySet<number>>(
        new Set(),
    );
    const scrollRef = useRef<HTMLDivElement>(null);
    const sentinelRef = useRef<HTMLDivElement>(null);
    const loadingRef = useRef(false);

    // A page visit brings the server's count; it wins over the local one.
    useEffect(() => {
        setUnread(initialUnread);
    }, [initialUnread]);

    const loadFirstPage = useCallback(async () => {
        loadingRef.current = true;
        setLoading(true);
        setLoadingFirst(true);
        setFailed(false);

        try {
            const page = await fetchNotifications();
            setItems(page.notifications);
            setHasMore(page.hasMore);
            setUnread(page.unreadCount);
        } catch {
            setFailed(true);
        } finally {
            loadingRef.current = false;
            setLoading(false);
            setLoadingFirst(false);
        }
    }, []);

    const loadMore = useCallback(async () => {
        const last = items.at(-1);

        if (loadingRef.current || !hasMore || last === undefined) {
            return;
        }

        loadingRef.current = true;
        setLoading(true);

        try {
            const page = await fetchNotifications(last.id);
            setItems((current) => [
                ...current,
                ...page.notifications.filter(
                    (item) => !current.some((known) => known.id === item.id),
                ),
            ]);
            setHasMore(page.hasMore);
            setUnread(page.unreadCount);
        } catch {
            setFailed(true);
        } finally {
            loadingRef.current = false;
            setLoading(false);
        }
    }, [hasMore, items]);

    useEffect(() => {
        if (open) {
            void loadFirstPage();
        }
    }, [open, loadFirstPage]);

    // Load the next page as the end of the list scrolls into view.
    useEffect(() => {
        const root = scrollRef.current;
        const sentinel = sentinelRef.current;

        if (!open || root === null || sentinel === null) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    void loadMore();
                }
            },
            { root, rootMargin: '80px' },
        );

        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [open, loadMore, items.length]);

    // New notifications over the reader's own channel.
    useEffect(() => {
        const echo = getEcho(realtime ?? undefined);

        if (echo === null) {
            return;
        }

        const stop = listenTo(echo.private(`user.${userId}`), {
            '.notification.created': (event: NotificationCreatedEvent) => {
                setUnread(event.unreadCount);
                setItems((current) =>
                    current.some((item) => item.id === event.notification.id)
                        ? current
                        : [event.notification, ...current],
                );
            },
        });
        const stopReconnect = onReconnect(echo, () => {
            void fetchNotifications()
                .then((page) => {
                    setUnread(page.unreadCount);
                    setItems(page.notifications);
                    setHasMore(page.hasMore);
                })
                .catch(() => undefined);
        });

        return () => {
            stop();
            stopReconnect();
        };
    }, [realtime, userId]);

    const openItem = async (item: AppNotification) => {
        try {
            const result = await openNotification(item.id);
            setUnread(result.unreadCount);
            setItems((current) =>
                current.map((known) =>
                    known.id === item.id ? { ...known, isRead: true } : known,
                ),
            );

            if (result.url === null) {
                setUnavailable((current) => new Set(current).add(item.id));

                return;
            }

            setOpen(false);
            router.visit(result.url);
        } catch {
            setUnavailable((current) => new Set(current).add(item.id));
        }
    };

    const markAllRead = async () => {
        try {
            const result = await markAllNotificationsRead();
            setUnread(result.unreadCount);
            setItems((current) =>
                current.map((item) => ({ ...item, isRead: true })),
            );
        } catch {
            setFailed(true);
        }
    };

    const badge = badgeLabel(unread);

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="relative grid h-11 w-11 place-items-center rounded-md transition-opacity hover:opacity-80"
                    aria-label={
                        badge === null
                            ? 'Notifications'
                            : `Notifications, ${unread} unread`
                    }
                    data-testid="notification-bell"
                >
                    <Bell className="size-5" aria-hidden />
                    {badge !== null ? (
                        <span
                            className="absolute top-1.5 right-1 grid h-4 min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-bold text-white"
                            data-testid="notification-badge"
                        >
                            {badge}
                        </span>
                    ) : null}
                </button>
            </DropdownMenuTrigger>

            {/*
             * Centred under the bell. Near the edge of a narrow screen it
             * slides back inside, 16px from the edge, rather than overflow.
             */}
            <DropdownMenuContent
                align="center"
                collisionPadding={16}
                className="w-[min(18rem,calc(100vw-2rem))] p-0"
            >
                <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
                    <span className="text-sm font-bold">Notifications</span>
                    <button
                        type="button"
                        onClick={() => void markAllRead()}
                        disabled={unread === 0}
                        className="cursor-pointer rounded-md px-2 py-1 text-xs font-medium text-secondary-foreground transition-opacity hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        Mark all as read
                    </button>
                </div>

                <div
                    ref={scrollRef}
                    className="max-h-[min(28rem,70vh)] overflow-y-auto overscroll-contain"
                >
                    {(loadingFirst ? [] : items).map((item) => (
                        <DropdownMenuItem
                            key={item.id}
                            onSelect={(event) => {
                                event.preventDefault();
                                void openItem(item);
                            }}
                            className="flex cursor-pointer items-start gap-2.5 rounded-none border-b border-border px-3 py-2.5 last:border-b-0"
                            data-testid="notification-item"
                        >
                            <span
                                className={`mt-1.5 size-2 shrink-0 rounded-full ${
                                    item.isRead
                                        ? 'bg-transparent'
                                        : 'bg-red-600'
                                }`}
                                aria-hidden
                            />
                            <span className="min-w-0 flex-1">
                                <span
                                    className={`block text-sm leading-snug ${
                                        item.isRead
                                            ? 'font-normal text-secondary-foreground'
                                            : 'font-bold text-foreground'
                                    }`}
                                >
                                    {item.isRead ? null : (
                                        <span className="sr-only">
                                            Unread:{' '}
                                        </span>
                                    )}
                                    {item.body}
                                </span>
                                <span className="mt-0.5 block text-xs text-secondary-foreground">
                                    {relativeTime(item.createdAt)}
                                </span>
                                {unavailable.has(item.id) ? (
                                    <span
                                        className="mt-1 block text-xs font-medium text-destructive"
                                        role="status"
                                    >
                                        {UNAVAILABLE}
                                    </span>
                                ) : null}
                            </span>
                        </DropdownMenuItem>
                    ))}

                    {items.length === 0 && !loading && !failed ? (
                        <p className="px-3 py-8 text-center text-sm text-secondary-foreground">
                            No notifications yet.
                        </p>
                    ) : null}

                    {failed ? (
                        <p className="px-3 py-4 text-center text-sm text-secondary-foreground">
                            Notifications could not be loaded.
                        </p>
                    ) : null}

                    {loading ? (
                        <NotificationSkeleton rows={loadingFirst ? 5 : 2} />
                    ) : null}

                    <div ref={sentinelRef} aria-hidden className="h-px" />
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
};

export default NotificationBell;
