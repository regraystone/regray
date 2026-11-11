import { readXsrfToken } from '@/lib/xsrf';
import {
    index as indexRoute,
    open as openRoute,
    readAll as readAllRoute,
} from '@/routes/notifications';

/**
 * The workspace bell's JSON endpoints (NotificationController). Where a
 * notification leads is never sent with it: opening it asks the server, which
 * answers with the page to go to, or null when the item is no longer available.
 */

/** What a notification is about. Mirrors App\Enums\NotificationType. */
export type NotificationType = 'message' | 'inquiry' | 'schedule';

export type AppNotification = {
    id: number;
    type: NotificationType;
    body: string;
    isRead: boolean;
    /** ISO 8601. */
    createdAt: string;
};

export type NotificationPage = {
    notifications: AppNotification[];
    hasMore: boolean;
    unreadCount: number;
};

/** The `.notification.created` event on the reader's `user.{id}` channel. */
export type NotificationCreatedEvent = {
    notification: AppNotification;
    unreadCount: number;
};

const request = async <T>(method: 'GET' | 'POST', url: string): Promise<T> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
    });

    if (!response.ok) {
        throw new Error(`The request failed (${response.status}).`);
    }

    return (await response.json()) as T;
};

/** The newest page, or the one older than `before`. */
export const fetchNotifications = (
    before?: number,
): Promise<NotificationPage> =>
    request(
        'GET',
        indexRoute.url(before === undefined ? {} : { query: { before } }),
    );

/** Read it, and learn where it leads: null when it is no longer available. */
export const openNotification = (
    id: number,
): Promise<{ url: string | null; unreadCount: number }> =>
    request('POST', openRoute.url(id));

export const markAllNotificationsRead = (): Promise<{ unreadCount: number }> =>
    request('POST', readAllRoute.url());
