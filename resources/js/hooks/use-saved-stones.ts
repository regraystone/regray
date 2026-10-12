import { useCallback, useEffect, useMemo, useSyncExternalStore } from 'react';
import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { toast } from 'sonner';

const SAVE_DEBOUNCE_MS = 400;

type PendingState = {
    desired: boolean;
    dirty: boolean;
    timer: number | null;
};

const pendingOverrides = new Map<string, PendingState>();
const listeners = new Set<() => void>();

const notify = (): void => {
    listeners.forEach((listener) => listener());
};

const getPending = (slug: string): PendingState | undefined => {
    return pendingOverrides.get(slug);
};

const setPending = (slug: string, state: PendingState): void => {
    pendingOverrides.set(slug, state);
    notify();
};

const clearPending = (slug: string): void => {
    pendingOverrides.delete(slug);
    notify();
};

/**
 * Build the effective saved state for a slug: server state with pending override on top.
 */
const effectiveSaved = (serverSlugs: string[], slug: string): boolean => {
    const pending = getPending(slug);
    if (pending) {
        return pending.desired;
    }
    return serverSlugs.includes(slug);
};

/**
 * Read the server slugs from the shared Inertia prop.
 */
const getServerSlugs = (): string[] => {
    try {
        const page = usePage();
        return (page.props.savedStoneSlugs as string[]) ?? [];
    } catch {
        return [];
    }
};

/**
 * Hook to read saved stone state and toggle saves with debounced server sync.
 *
 * @param refresh - Additional Inertia props to reload with each server request.
 * @returns { savedIds: Set<string>, toggleSave: (slug: string) => void, canSave: boolean }
 */
export function useSavedStones(options?: { refresh?: string[] }): {
    savedIds: Set<string>;
    toggleSave: (slug: string) => void;
    canSave: boolean;
} {
    const serverSlugs = useSyncExternalStore(
        (listener) => {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        () => getServerSlugs(),
        () => [],
    );

    const { can } = usePermissions();
    const canSave = can('saved.view') && can('saved.manage');

    const savedIds = useMemo(() => {
        const base = new Set(serverSlugs);
        for (const [slug, pending] of pendingOverrides) {
            if (pending.desired) {
                base.add(slug);
            } else {
                base.delete(slug);
            }
        }
        return base;
    }, [serverSlugs]);

    // Clean up old localStorage key on mount
    useEffect(() => {
        try {
            localStorage.removeItem('regray:saved-stones');
        } catch {
            // Ignore quota / privacy-mode failures
        }
    }, []);

    const toggleSave = useCallback(
        (slug: string) => {
            if (!canSave) {
                return;
            }

            const currentEffective = effectiveSaved(serverSlugs, slug);
            const nextDesired = !currentEffective;

            // Flip the pending override immediately for instant UI feedback
            getPending(slug);
            const timer = window.setTimeout(() => {
                flushPending(slug, options?.refresh);
            }, SAVE_DEBOUNCE_MS);

            setPending(slug, {
                desired: nextDesired,
                dirty: false,
                timer,
            });
        },
        [canSave, serverSlugs],
    );

    return { savedIds, toggleSave, canSave };
}

/**
 * Send the pending state to the server if it differs from the server state.
 * If the user clicked again while the request was in flight (dirty flag),
 * restart the debounce window with the fresh state.
 */
function flushPending(slug: string, refresh?: string[]): void {
    const pending = getPending(slug);
    if (!pending) {
        return;
    }

    // If we got here, the timer fired. Check if user clicked again meanwhile.
    if (pending.dirty) {
        // User clicked again while request was in flight; timer was already restarted.
        // This flush call is stale; the new timer will handle it.
        return;
    }

    // Get fresh server state
    let serverSlugs: string[] = [];
    try {
        const page = usePage();
        serverSlugs = (page.props.savedStoneSlugs as string[]) ?? [];
    } catch {
        serverSlugs = [];
    }

    const serverState = serverSlugs.includes(slug);
    const desiredState = pending.desired;

    // If desired matches server, nothing to do
    if (desiredState === serverState) {
        clearPending(slug);
        return;
    }

    // Send the request
    const method = desiredState ? 'put' : 'delete';
    const only = ['savedStoneSlugs', ...(refresh ?? [])];

    router[method](`/saved/${slug}`, {
        preserveScroll: true,
        preserveState: true,
        only,
    });

    // Mark as dirty so a new click during flight restarts the debounce
    setPending(slug, { ...pending, dirty: true });

    // We can't await the Inertia request, but we can use a microtask to check
    // if the user clicked again. If they did, dirty will be true and we skip clearing.
    Promise.resolve()
        .then(() => {
            const after = getPending(slug);
            if (after && !after.dirty) {
                clearPending(slug);
            }
        })
        .catch(() => {
            // On error, drop override and show toast; UI falls back to server state
            clearPending(slug);
            toast.error('Failed to update saved stones. Please try again.');
        });
}
