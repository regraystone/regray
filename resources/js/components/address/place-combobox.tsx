import { Check, ChevronDown, LoaderCircle } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useRef,
    useState,
    type KeyboardEvent,
    type Ref,
    type UIEvent,
} from 'react';
import { Input } from '@/components/ui/input';
import type { PhPlace } from '@/lib/ph-address';
import type { PlacePage } from '@/lib/ph-places-api';
import { cn } from '@/lib/utils';

/** How long typing pauses before the search is asked. */
const DEBOUNCE_MS = 250;

/** An entry offered above the results, such as "Not in a province". */
export type PlaceExtraOption = { key: string; label: string };

type PlaceComboboxProps = {
    id: string;
    /** The place picked, or null. The box shows its name whenever it is not being searched. */
    value: PhPlace | null;
    /** The entry of `extraOptions` picked instead of a place, if any. */
    extraValue?: string | null;
    /** One page of places matching `query`; a page after the first as the list is scrolled. */
    load: (
        query: string,
        page: number,
        signal: AbortSignal,
    ) => Promise<PlacePage>;
    /** Changes whenever what `load` reads from changes (the parent's code), to search again. */
    loadKey: string;
    extraOptions?: PlaceExtraOption[];
    listLabel: string;
    placeholder?: string;
    disabled?: boolean;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
    ref?: Ref<HTMLInputElement>;
    onChange: (place: PhPlace) => void;
    onExtra?: (key: string) => void;
};

type Row =
    | { kind: 'extra'; key: string; label: string }
    | { kind: 'place'; place: PhPlace };

/**
 * A search box that picks one place of one level from the server, a page at
 * a time, following the ARIA combobox pattern: the input owns the search, a
 * listbox under it, and `aria-activedescendant` moves the highlight while
 * focus stays in the box.
 *
 * It never takes free text: what is typed only searches, and leaving the box
 * without picking puts the picked place's name back. Scrolling to the end of
 * the list asks for the next page.
 */
const PlaceCombobox = ({
    id,
    value,
    extraValue = null,
    load,
    loadKey,
    extraOptions = [],
    listLabel,
    placeholder,
    disabled = false,
    'aria-describedby': describedBy,
    'aria-invalid': invalid,
    ref: forwardedRef,
    onChange,
    onExtra,
}: PlaceComboboxProps) => {
    const listboxId = `place-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [results, setResults] = useState<PhPlace[]>([]);
    const [nextPage, setNextPage] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState<string | null>(null);
    const [activeIndex, setActiveIndex] = useState(0);
    const requestRef = useRef<AbortController | null>(null);
    // Read when asked rather than watched: a parent's redraw is no new search.
    const loadRef = useRef(load);

    useEffect(() => {
        loadRef.current = load;
    }, [load]);

    const shownName =
        extraValue !== null
            ? (extraOptions.find((option) => option.key === extraValue)
                  ?.label ?? '')
            : (value?.name ?? '');

    const rows: Row[] = [
        ...(query.trim() === ''
            ? extraOptions.map((option): Row => ({ kind: 'extra', ...option }))
            : []),
        ...results.map((place): Row => ({ kind: 'place', place })),
    ];
    const hasActive = activeIndex >= 0 && activeIndex < rows.length;

    const setInputRef = useCallback(
        (element: HTMLInputElement | null) => {
            inputRef.current = element;

            if (typeof forwardedRef === 'function') {
                forwardedRef(element);
            } else if (forwardedRef) {
                forwardedRef.current = element;
            }
        },
        [forwardedRef],
    );

    const fetchPage = useCallback((text: string, page: number) => {
        requestRef.current?.abort();
        const controller = new AbortController();
        requestRef.current = controller;
        setLoading(true);
        setFailed(null);

        loadRef
            .current(text, page, controller.signal)
            .then((result) => {
                setResults((current) =>
                    page === 1 ? result.data : [...current, ...result.data],
                );
                setNextPage(result.nextPage);
            })
            .catch((error: unknown) => {
                if (
                    !(error instanceof DOMException) ||
                    error.name !== 'AbortError'
                ) {
                    setFailed(
                        error instanceof Error
                            ? error.message
                            : "We couldn't load the places. Try again.",
                    );
                }
            })
            .finally(() => {
                if (requestRef.current === controller) {
                    setLoading(false);
                }
            });
    }, []);

    // A new parent is a new list: what was loaded belongs to the old one.
    useEffect(() => {
        requestRef.current?.abort();
        setResults([]);
        setNextPage(null);
    }, [loadKey]);

    // Search as the reader types, once they pause; on opening, the first page.
    useEffect(() => {
        if (!open || disabled) {
            return;
        }

        const timer = window.setTimeout(
            () => fetchPage(searching ? query : '', 1),
            searching ? DEBOUNCE_MS : 0,
        );

        return () => window.clearTimeout(timer);
    }, [disabled, fetchPage, open, query, searching, loadKey]);

    useEffect(() => () => requestRef.current?.abort(), []);

    const close = useCallback(() => {
        setOpen(false);
        setSearching(false);
        setQuery('');
    }, []);

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnOutsideClick = (event: PointerEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                close();
            }
        };

        document.addEventListener('pointerdown', closeOnOutsideClick);

        return () =>
            document.removeEventListener('pointerdown', closeOnOutsideClick);
    }, [close, open]);

    useEffect(() => {
        if (open && hasActive) {
            document
                .getElementById(`${listboxId}-option-${activeIndex}`)
                ?.scrollIntoView({ block: 'nearest' });
        }
    }, [activeIndex, hasActive, listboxId, open]);

    const openList = () => {
        if (disabled) {
            return;
        }

        setOpen(true);
        setActiveIndex(0);
    };

    const choose = (row: Row) => {
        if (row.kind === 'extra') {
            onExtra?.(row.key);
        } else {
            onChange(row.place);
        }

        close();
        inputRef.current?.focus();
    };

    const onScroll = (event: UIEvent<HTMLUListElement>) => {
        const list = event.currentTarget;

        if (
            nextPage !== null &&
            !loading &&
            list.scrollTop + list.clientHeight >= list.scrollHeight - 40
        ) {
            fetchPage(searching ? query : '', nextPage);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            if (open) {
                event.preventDefault();
                close();
            }

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (!open) {
                openList();

                return;
            }

            if (rows.length === 0) {
                return;
            }

            const step = event.key === 'ArrowDown' ? 1 : -1;

            setActiveIndex((current) => {
                const next = current + step;

                return next < 0
                    ? rows.length - 1
                    : next >= rows.length
                      ? 0
                      : next;
            });

            return;
        }

        if (event.key === 'Enter' && open) {
            if (hasActive) {
                event.preventDefault();
                choose(rows[activeIndex]);
            }

            return;
        }

        if (event.key === 'Tab') {
            close();
        }
    };

    const isChosen = (row: Row): boolean =>
        row.kind === 'extra'
            ? row.key === extraValue
            : extraValue === null && row.place.code === value?.code;

    return (
        <div ref={rootRef} className="relative">
            <Input
                ref={setInputRef}
                id={id}
                role="combobox"
                type="text"
                autoComplete="off"
                disabled={disabled}
                aria-expanded={open}
                aria-controls={open ? listboxId : undefined}
                aria-autocomplete="list"
                aria-activedescendant={
                    open && hasActive
                        ? `${listboxId}-option-${activeIndex}`
                        : undefined
                }
                aria-describedby={describedBy}
                aria-invalid={invalid}
                value={searching ? query : shownName}
                placeholder={placeholder}
                onChange={(event) => {
                    setOpen(true);
                    setSearching(true);
                    setActiveIndex(0);
                    setQuery(event.target.value);
                }}
                onFocus={(event) => {
                    openList();
                    event.target.select();
                }}
                onKeyDown={onKeyDown}
                className="rounded-none pr-9"
            />

            {loading && open ? (
                <LoaderCircle
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin opacity-60"
                />
            ) : (
                <ChevronDown
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 opacity-50"
                />
            )}

            {open ? (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label={listLabel}
                    aria-busy={loading}
                    onScroll={onScroll}
                    className="absolute top-[calc(100%+4px)] right-0 left-0 z-30 max-h-60 overflow-y-auto rounded-md border border-sidebar-border bg-background"
                >
                    {rows.map((row, index) => (
                        <li
                            key={
                                row.kind === 'extra' ? row.key : row.place.code
                            }
                            id={`${listboxId}-option-${index}`}
                            role="option"
                            aria-selected={isChosen(row)}
                            onMouseDown={(event) => event.preventDefault()}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={() => choose(row)}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm',
                                index === activeIndex && 'bg-muted',
                                row.kind === 'extra' && 'italic',
                            )}
                        >
                            <span className="min-w-0 flex-1 break-words">
                                {row.kind === 'extra'
                                    ? row.label
                                    : row.place.name}
                            </span>
                            {isChosen(row) ? (
                                <Check
                                    aria-hidden
                                    className="size-4 shrink-0"
                                />
                            ) : null}
                        </li>
                    ))}

                    {rows.length === 0 && !loading ? (
                        <li className="px-3 py-2 text-sm text-secondary-foreground">
                            {failed ?? 'Nothing matches that.'}
                        </li>
                    ) : null}

                    {rows.length > 0 && failed !== null ? (
                        <li className="px-3 py-2 text-sm text-destructive">
                            {failed}
                        </li>
                    ) : null}

                    {loading && rows.length > 0 ? (
                        <li className="px-3 py-2 text-sm text-secondary-foreground">
                            Loading…
                        </li>
                    ) : null}
                </ul>
            ) : null}
        </div>
    );
};

export default PlaceCombobox;
