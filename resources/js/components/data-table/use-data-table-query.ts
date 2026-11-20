import { router } from '@inertiajs/react';
import { useState } from 'react';
import { nextQuery, type QueryValue } from '@/lib/data-table';

/**
 * How a list page changes its query: one helper for the search, every filter
 * and the pager, so a page never writes its own reload.
 *
 * `update` keeps every other value in the URL, starts again from page 1
 * unless it is given a page (nextQuery), and reloads only `only` (the list and
 * its filters), keeping the page's state and scroll and replacing the history
 * entry. `isLoading` is true while that runs.
 */
export const useDataTableQuery = (only: string[]) => {
    const [isLoading, setIsLoading] = useState(false);

    const update = (changes: Record<string, QueryValue>) => {
        const current = Object.fromEntries(
            new URLSearchParams(window.location.search),
        );

        router.get(window.location.pathname, nextQuery(current, changes), {
            only,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setIsLoading(true),
            onFinish: () => setIsLoading(false),
        });
    };

    return { update, isLoading };
};
