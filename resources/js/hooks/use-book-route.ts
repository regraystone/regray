import { usePage } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { permissions } from '@/layouts/workspace-layout';
import { book as agendaBook } from '@/routes/agenda';
import { book as profileAgendaBook } from '@/routes/profile/agenda';

/**
 * The booking route for this user, or null if they may not book.
 *
 * Reading the URL rather than the permissions matters because admin holds both
 * doors and should stay on the one they are using. The door they are on is
 * determined by whether the current page is under `/profile`.
 */
export function useBookRoute() {
    const { can } = usePermissions();
    const { url } = usePage();

    if (!can(permissions.agendaCreate)) {
        return null;
    }

    if (url.startsWith('/profile')) {
        return profileAgendaBook;
    }

    return agendaBook;
}
