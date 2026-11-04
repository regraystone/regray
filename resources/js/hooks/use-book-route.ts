import { usePage } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { book as scheduleBook } from '@/routes/schedule';
import { show as showProfile } from '@/routes/profile';
import { book as profileScheduleBook } from '@/routes/profile/schedule';

/**
 * The booking route for this user, or null if they may not book.
 *
 * Reading the URL rather than the permissions matters because admin holds both
 * doors and should stay on the one they are using. The door they are on is
 * determined by whether the current page is under the Profile hub.
 */
export function useBookRoute() {
    const { can } = usePermissions();
    const { url } = usePage();

    if (!can(permissions.scheduleCreate)) {
        return null;
    }

    if (url.startsWith(showProfile.url())) {
        return profileScheduleBook;
    }

    return scheduleBook;
}
