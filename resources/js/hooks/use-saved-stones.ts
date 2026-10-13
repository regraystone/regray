import {
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useSyncExternalStore,
} from 'react';
import { router } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { toast } from 'sonner';

const SAVE_DEBOUNCE_MS = 350;
const EMPTY_SLUGS: string[] = [];

type PendingState = {
    desired: boolean;
    timer: number | null;
    inFlight: boolean;
};

const pendingOverrides = new Map<string, PendingState>();
const listeners = new Set<() => void>();

/**
 * Store revision bump so `useSyncExternalStore` re-renders consumers whenever
 * an optimistic override changes, independent of the server snapshot.
 */
let revision = 0;

const notify = (): void => {
    revision += 1;
    listeners.forEach((listener) => listener());
};

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);
    return () => {
        listeners.delete(listener);
    };
};

const getRevision = (): number => revision;

const getPending = (slug: string): PendingState | undefined => {
    return pendingOverrides.get(slug);
};

const setPending = (slug: string, state: PendingState): void => {
    pendingOverrides.set(slug, state);
    notify();
};

const clearPending = (slug: string): void => {
    const pending = pendingOverrides.get(slug);
    if (!pending) {
        return;
    }

    if (pending.timer != null) {
        window.clearTimeout(pending.timer);
    }

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
 * Hook to read saved stone state and toggle saves with a per-slug trailing
 * debounce. Only the final desired state is sent, and never more than one
 * request per slug at a time.
 *
 * @param refresh - Additional Inertia props to reload with each server request.
 * @returns { savedIds: Set<string>, toggleSave: (slug: string) => void, canSave: boolean }
 */
export function useSavedStones(options?: { refresh?: string[] }): {
    savedIds: Set<string>;
    toggleSave: (slug: string) => void;
    canSave: boolean;
} {
    const page = usePage<{ savedStoneSlugs: string[] }>();
    const serverSlugs = page.props.savedStoneSlugs ?? EMPTY_SLUGS;

    // Keep the latest server state available to the debounced flush, which runs
    // outside of render and therefore cannot call `usePage()` itself.
    const serverSlugsRef = useRef(serverSlugs);
    useEffect(() => {
        serverSlugsRef.current = serverSlugs;
    }, [serverSlugs]);

    // Re-render when optimistic overrides change so the heart flips instantly.
    const storeRevision = useSyncExternalStore(
        subscribe,
        getRevision,
        getRevision,
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
    }, [serverSlugs, storeRevision]);

    const flushPendingRef = useRef<(slug: string) => void>(() => {});

    /**
     * Send exactly one request for the slug. The override is kept until the
     * response proves the server agrees (or the request fails), so the heart
     * never flickers between tap and confirmation.
     */
    const sendRequest = useCallback(
        (slug: string, desired: boolean): void => {
            const pending = getPending(slug);
            if (!pending) {
                return;
            }

            pending.inFlight = true;
            pending.timer = null;

            const only = ['savedStoneSlugs', ...(options?.refresh ?? [])];

            const requestOptions = {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only,
                // The ref is updated from the response itself because it has not
                // re-rendered yet when `onFinish` runs.
                onSuccess: (response: {
                    props: { savedStoneSlugs?: string[] };
                }): void => {
                    serverSlugsRef.current =
                        response.props.savedStoneSlugs ?? EMPTY_SLUGS;
                },
                onFinish: (): void => {
                    const after = getPending(slug);
                    if (!after) {
                        return;
                    }

                    after.inFlight = false;

                    // A newer tap is still debouncing; let its timer decide.
                    if (after.timer != null) {
                        return;
                    }

                    flushPendingRef.current(slug);
                },
                onError: (): void => {
                    clearPending(slug);
                    toast.error(
                        'Failed to update saved stones. Please try again.',
                    );
                },
            };

            if (desired) {
                router.put(`/saved/${slug}`, {}, requestOptions);
            } else {
                router.delete(`/saved/${slug}`, requestOptions);
            }
        },
        [options?.refresh],
    );

    /**
     * Compare the final desired state to the latest server state and send at
     * most one request. If the desired state already matches, drop the override.
     */
    const flushPending = useCallback(
        (slug: string): void => {
            const pending = getPending(slug);
            if (!pending) {
                return;
            }

            pending.timer = null;

            // A request for this slug is still in flight; its finish handler
            // will re-check and send again if the state still differs.
            if (pending.inFlight) {
                return;
            }

            if (pending.desired === serverSlugsRef.current.includes(slug)) {
                clearPending(slug);
                return;
            }

            sendRequest(slug, pending.desired);
        },
        [sendRequest],
    );

    useEffect(() => {
        flushPendingRef.current = flushPending;
    }, [flushPending]);

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

            // Flip the override immediately for instant UI feedback.
            const nextDesired = !effectiveSaved(serverSlugsRef.current, slug);

            // Restart this slug's debounce window instead of stacking timers.
            const existing = getPending(slug);
            if (existing?.timer != null) {
                window.clearTimeout(existing.timer);
            }

            const timer = window.setTimeout(() => {
                flushPendingRef.current(slug);
            }, SAVE_DEBOUNCE_MS);

            setPending(slug, {
                desired: nextDesired,
                timer,
                inFlight: existing?.inFlight ?? false,
            });
        },
        [canSave],
    );

    return { savedIds, toggleSave, canSave };
}
