import { router } from '@inertiajs/react';
import { forgetBrowserStorage } from '@/lib/browser-storage';
import { logout } from '@/routes';

/**
 * The logout request, shared by Log out and Switch account.
 *
 * Drops Inertia's cached pages first, so nothing of the account is shown again
 * from the cache, and once the server has ended the session forgets what the
 * account kept in this browser. `onSuccess` runs after that, for a caller with
 * somewhere else to go than where the server sends a logged-out reader.
 */
export const logOut = ({
    onSuccess,
    onFinish,
}: {
    onSuccess?: () => void;
    onFinish?: () => void;
} = {}): void => {
    router.flushAll();
    router.post(
        logout.url(),
        {},
        {
            onSuccess: () => {
                forgetBrowserStorage();
                onSuccess?.();
            },
            onFinish: () => onFinish?.(),
        },
    );
};
