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

/**
 * The optimistic overrides for one kind of saved thing (stones, furniture).
 * Each kind has its own, so a stone and a piece of furniture that share a
 * slug never share a heart.
 */
const createStore = () => {
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

    return {
        pendingOverrides,
        subscribe: (listener: () => void): (() => void) => {
            listeners.add(listener);
            return () => {
                listeners.delete(listener);
            };
        },
        getRevision: (): number => revision,
        getPending: (slug: string): PendingState | undefined =>
            pendingOverrides.get(slug),
        setPending: (slug: string, state: PendingState): void => {
            pendingOverrides.set(slug, state);
            notify();
        },
        clearPending: (slug: string): void => {
            const pending = pendingOverrides.get(slug);
            if (!pending) {
                return;
            }

            if (pending.timer != null) {
                window.clearTimeout(pending.timer);
            }

            pendingOverrides.delete(slug);
            notify();
        },
    };
};

type SavedStore = ReturnType<typeof createStore>;

/** What differs between the kinds: where the slugs come from and where a save goes. */
type SavedKind = {
    store: SavedStore;
    /** The shared Inertia prop that lists the saved slugs. */
    prop: 'savedStoneSlugs' | 'savedFurnitureSlugs';
    url: (slug: string) => string;
    errorMessage: string;
};

const stones: SavedKind = {
    store: createStore(),
    prop: 'savedStoneSlugs',
    url: (slug) => `/saved/${slug}`,
    errorMessage: 'Failed to update saved stones. Please try again.',
};

const furniture: SavedKind = {
    store: createStore(),
    prop: 'savedFurnitureSlugs',
    url: (slug) => `/saved/furniture/${slug}`,
    errorMessage: 'Failed to update saved furniture. Please try again.',
};

type SavedItems = {
    savedIds: Set<string>;
    toggleSave: (slug: string) => void;
    canSave: boolean;
};

/**
 * Read one kind's saved state and toggle saves with a per-slug trailing
 * debounce. Only the final desired state is sent, and never more than one
 * request per slug at a time.
 */
function useSavedItems(
    kind: SavedKind,
    options?: { refresh?: string[] },
): SavedItems {
    const { store, prop } = kind;
    const page = usePage<Record<SavedKind['prop'], string[] | undefined>>();
    const serverSlugs = page.props[prop] ?? EMPTY_SLUGS;

    // Keep the latest server state available to the debounced flush, which runs
    // outside of render and therefore cannot call `usePage()` itself.
    const serverSlugsRef = useRef(serverSlugs);
    useEffect(() => {
        serverSlugsRef.current = serverSlugs;
    }, [serverSlugs]);

    // Re-render when optimistic overrides change so the heart flips instantly.
    const storeRevision = useSyncExternalStore(
        store.subscribe,
        store.getRevision,
        store.getRevision,
    );

    const { can } = usePermissions();
    const canSave = can('saved.view') && can('saved.manage');

    const savedIds = useMemo(() => {
        const base = new Set(serverSlugs);
        for (const [slug, pending] of store.pendingOverrides) {
            if (pending.desired) {
                base.add(slug);
            } else {
                base.delete(slug);
            }
        }
        return base;
    }, [serverSlugs, storeRevision, store]);

    const flushPendingRef = useRef<(slug: string) => void>(() => {});

    /**
     * Send exactly one request for the slug. The override is kept until the
     * response proves the server agrees (or the request fails), so the heart
     * never flickers between tap and confirmation.
     */
    const sendRequest = useCallback(
        (slug: string, desired: boolean): void => {
            const pending = store.getPending(slug);
            if (!pending) {
                return;
            }

            pending.inFlight = true;
            pending.timer = null;

            const only = [prop, ...(options?.refresh ?? [])];

            const requestOptions = {
                preserveScroll: true,
                preserveState: true,
                async: true,
                only,
                // The ref is updated from the response itself because it has not
                // re-rendered yet when `onFinish` runs.
                onSuccess: (response: {
                    props: Partial<Record<SavedKind['prop'], string[]>>;
                }): void => {
                    serverSlugsRef.current =
                        response.props[prop] ?? EMPTY_SLUGS;
                },
                onFinish: (): void => {
                    const after = store.getPending(slug);
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
                    store.clearPending(slug);
                    toast.error(kind.errorMessage);
                },
            };

            if (desired) {
                router.put(kind.url(slug), {}, requestOptions);
            } else {
                router.delete(kind.url(slug), requestOptions);
            }
        },
        [options?.refresh, kind, store, prop],
    );

    /**
     * Compare the final desired state to the latest server state and send at
     * most one request. If the desired state already matches, drop the override.
     */
    const flushPending = useCallback(
        (slug: string): void => {
            const pending = store.getPending(slug);
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
                store.clearPending(slug);
                return;
            }

            sendRequest(slug, pending.desired);
        },
        [sendRequest, store],
    );

    useEffect(() => {
        flushPendingRef.current = flushPending;
    }, [flushPending]);

    const toggleSave = useCallback(
        (slug: string) => {
            if (!canSave) {
                return;
            }

            // Flip the override immediately for instant UI feedback.
            const existing = store.getPending(slug);
            const currentlySaved = existing
                ? existing.desired
                : serverSlugsRef.current.includes(slug);

            // Restart this slug's debounce window instead of stacking timers.
            if (existing?.timer != null) {
                window.clearTimeout(existing.timer);
            }

            const timer = window.setTimeout(() => {
                flushPendingRef.current(slug);
            }, SAVE_DEBOUNCE_MS);

            store.setPending(slug, {
                desired: !currentlySaved,
                timer,
                inFlight: existing?.inFlight ?? false,
            });
        },
        [canSave, store],
    );

    return { savedIds, toggleSave, canSave };
}

/**
 * Saved stones: which are saved, and a debounced toggle.
 *
 * @param refresh - Additional Inertia props to reload with each server request.
 */
export function useSavedStones(options?: { refresh?: string[] }): SavedItems {
    // Clean up the old localStorage key on mount.
    useEffect(() => {
        try {
            localStorage.removeItem('regray:saved-stones');
        } catch {
            // Ignore quota / privacy-mode failures
        }
    }, []);

    return useSavedItems(stones, options);
}

/**
 * Saved furniture, the same as `useSavedStones` for furniture slugs.
 *
 * @param refresh - Additional Inertia props to reload with each server request.
 */
export function useSavedFurniture(options?: {
    refresh?: string[];
}): SavedItems {
    return useSavedItems(furniture, options);
}
