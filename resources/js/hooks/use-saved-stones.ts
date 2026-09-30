import { useCallback, useEffect, useSyncExternalStore } from 'react';
import { stones } from '@/data/constant';

const STORAGE_KEY = 'regray:saved-stones';
// Bumped when the catalogue's ids change, so stored picks are re-seeded instead
// of being carried over onto stones that no longer exist.
const SEED_VERSION = 3;

const DEFAULT_SAVED_IDS = stones.slice(0, 6).map((stone) => stone.id);

const STONE_IDS = new Set(stones.map((stone) => stone.id));

const EMPTY_SET = new Set<string>();

let savedIds = EMPTY_SET;
let hydrated = false;
const listeners = new Set<() => void>();

const readStorage = (): Set<string> => {
    if (typeof window === 'undefined') {
        return EMPTY_SET;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw === null) {
            const seeded = new Set(DEFAULT_SAVED_IDS);

            writeStorage(seeded);

            return seeded;
        }

        const parsed: unknown = JSON.parse(raw);
        let ids: string[] = [];
        let storedVersion: number | null = null;

        if (Array.isArray(parsed)) {
            ids = parsed.filter((id): id is string => typeof id === 'string');
            storedVersion = null;
        } else if (
            typeof parsed === 'object' &&
            parsed !== null &&
            Array.isArray((parsed as { ids?: unknown }).ids)
        ) {
            const record = parsed as { ids: string[]; version: unknown };

            ids = record.ids.filter(
                (id): id is string => typeof id === 'string',
            );
            storedVersion =
                typeof record.version === 'number' ? record.version : null;
        }

        if (storedVersion !== null && storedVersion < SEED_VERSION) {
            const upgraded = new Set(DEFAULT_SAVED_IDS);

            writeStorage(upgraded);

            return upgraded;
        }

        // A stone that has left the catalogue cannot be rendered as a card, so
        // its id is dropped rather than left to inflate the saved count.
        const known = ids.filter((id) => STONE_IDS.has(id));
        const current = new Set(known);

        if (current.size !== ids.length) {
            writeStorage(current);
        }

        return current;
    } catch {
        return EMPTY_SET;
    }
};

const writeStorage = (ids: Set<string>): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ version: SEED_VERSION, ids: [...ids] }),
        );
    } catch {
        // Ignore quota / privacy-mode failures; state still lives in memory.
    }
};

const hydrate = (): void => {
    if (hydrated) {
        return;
    }

    hydrated = true;
    savedIds = readStorage();
    listeners.forEach((listener) => listener());
};

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = (): Set<string> => savedIds;

const getServerSnapshot = (): Set<string> => EMPTY_SET;

export function useSavedStones(): {
    savedIds: Set<string>;
    toggleSave: (id: string) => void;
} {
    const saved = useSyncExternalStore(
        subscribe,
        getSnapshot,
        getServerSnapshot,
    );

    useEffect(() => {
        hydrate();
    }, []);

    return {
        savedIds: saved,
        toggleSave: useCallback((id: string) => {
            const next = new Set(savedIds);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            savedIds = next;
            writeStorage(savedIds);
            listeners.forEach((listener) => listener());
        }, []),
    };
}
