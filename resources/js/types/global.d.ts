import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            savedStoneSlugs: string[];
            /** Unread messages across the user's conversations, for the Message link. */
            unreadMessageCount: number;
            /** Unread notifications, for the bell's badge. */
            unreadNotificationCount: number;
            [key: string]: unknown;
        };
    }
}
