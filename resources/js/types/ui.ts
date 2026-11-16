import type { ReactNode } from 'react';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    /** The small uppercase line above the title ("Welcome back"). */
    eyebrow?: string;
    title?: string;
    description?: string;
};
