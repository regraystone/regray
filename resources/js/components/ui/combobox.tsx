import { Check, ChevronDown } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type KeyboardEvent,
    type Ref,
} from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type ComboboxProps = {
    id: string;
    /**
     * The text in the box, which is also the query the list is filtered by.
     *
     * One string for both, rather than a separate query held here, so the field
     * has a single source of truth: the page can round-trip it through its own
     * state, and whatever is in the box when the reader presses Continue is what
     * gets validated.
     */
    value: string;
    /** The full set, shown when the field is opened rather than searched. */
    options: readonly string[];
    /** Narrows `options` to what the reader has typed. */
    filter: (query: string) => string[];
    /** What the list is, for a screen reader landing on it. */
    listLabel: string;
    placeholder?: string;
    /** Shown when the query matches nothing, which is a real answer. */
    emptyMessage?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
    className?: string;
    /**
     * The text input itself, not the wrapper.
     *
     * Forwarded so whoever holds this field can still send focus to it, which is
     * what a validation run does when the reader is sent to the field they got
     * wrong.
     */
    ref?: Ref<HTMLInputElement>;
    onChange: (value: string) => void;
};

/**
 * A text field that picks from a list.
 *
 * Built here rather than pulled in, because the project has no `cmdk` and no
 * popover to hang one off. It follows the combobox pattern: a real text input
 * that owns the filtering, a listbox under it, and `aria-activedescendant` so
 * the arrow keys move a highlight through the list without focus ever leaving
 * the field.
 *
 * The list opens on focus and on typing, and closes on Escape, on a click
 * elsewhere, or on a choice. It never closes on blur, because blur is what a
 * click on an option causes, and closing on it would drop the click.
 */
const Combobox = ({
    id,
    value,
    options,
    filter,
    listLabel,
    placeholder,
    emptyMessage = 'No matches.',
    'aria-describedby': describedBy,
    'aria-invalid': invalid,
    className,
    ref: forwardedRef,
    onChange,
}: ComboboxProps) => {
    /*
     * React's generated ids contain characters that are legal in an id but not
     * in a selector, and the listbox and its rows are addressed by id. Stripping
     * to identifier characters keeps `getElementById`, `aria-activedescendant`
     * and a test's `querySelector('#...')` all working off the same string.
     */
    const generatedId = useId();
    const listboxId = `combobox-${generatedId.replace(/[^a-zA-Z0-9_-]/g, '')}`;

    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);

    /*
     * Whether the list is the whole set or the matches for what is typed.
     *
     * Opening a field that already holds a choice should show every option with
     * that one ticked, not the single row the text happens to match: otherwise
     * reopening after a choice offers nothing to move to, the arrow keys look
     * dead, and the tick has no other options to distinguish it from. Typing is a
     * search, so it always narrows. The search term stays the page's value either
     * way, so the answer still lives in exactly one place.
     */
    const [browsingAll, setBrowsingAll] = useState(false);

    const matches = useMemo(
        () => (browsingAll ? [...options] : filter(value)),
        [browsingAll, filter, options, value],
    );

    /*
     * The highlight is only meaningful against a row that exists. A query that
     * matched nothing leaves the index where it was, which points at nothing, so
     * everything that reads it has to check the range rather than trust it.
     */
    const hasActive = activeIndex >= 0 && activeIndex < matches.length;

    /*
     * The same node is kept in two places: here, because choosing an option puts
     * focus back in the field, and in the caller's ref, because a validation run
     * that fails has to be able to move focus to it.
     */
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

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOnOutsideClick = (event: PointerEvent) => {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', closeOnOutsideClick);

        return () =>
            document.removeEventListener('pointerdown', closeOnOutsideClick);
    }, [open]);

    // Keeps the highlighted row in view while arrowing through a long list.
    useEffect(() => {
        if (!open || !hasActive) {
            return;
        }

        document
            .getElementById(`${listboxId}-option-${activeIndex}`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex, hasActive, listboxId, open]);

    /*
     * Opening shows the whole set and starts the highlight on the row the field
     * already holds, so a field that has been answered shows where the answer is
     * rather than jumping the highlight to the top of the alphabet. It is set here
     * rather than in an effect on the match list: an effect would reset the
     * highlight every time the field re-rendered, which would fight the arrow keys
     * rather than serve them.
     */
    const openList = () => {
        setOpen(true);
        setBrowsingAll(true);

        const chosen = options.indexOf(value);

        setActiveIndex(chosen === -1 ? 0 : chosen);
    };

    const choose = (option: string) => {
        onChange(option);
        setOpen(false);
        // Focus stays put, so the answer can be corrected by typing rather than
        // by starting the whole field again.
        inputRef.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            if (!open) {
                return;
            }

            event.preventDefault();
            setOpen(false);

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();

            if (!open) {
                openList();

                return;
            }

            if (matches.length === 0) {
                return;
            }

            const step = event.key === 'ArrowDown' ? 1 : -1;

            setActiveIndex((current) => {
                const next = current + step;

                // Wraps, so holding an arrow key cycles rather than sticking.
                if (next < 0) {
                    return matches.length - 1;
                }

                if (next >= matches.length) {
                    return 0;
                }

                return next;
            });

            return;
        }

        if (event.key === 'Enter' && open) {
            // A query that matched nothing has no row to take, and Enter then
            // falls through to submit the form, which is the honest outcome for
            // an answer that was never completed.
            if (!hasActive) {
                return;
            }

            // Without this, Enter would submit the form from a half-typed answer
            // instead of choosing the highlighted match.
            event.preventDefault();
            choose(matches[activeIndex]);

            return;
        }

        if (event.key === 'Tab') {
            setOpen(false);
        }
    };

    return (
        <div ref={rootRef} className="relative">
            <Input
                ref={setInputRef}
                id={id}
                role="combobox"
                type="text"
                autoComplete="off"
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
                value={value}
                placeholder={placeholder}
                onChange={(event) => {
                    // Typing always reopens: the list is the answer to a keystroke.
                    // It also always searches, and starts at the top of what is left
                    // to choose from.
                    setOpen(true);
                    setBrowsingAll(false);
                    setActiveIndex(0);
                    onChange(event.target.value);
                }}
                onFocus={(event) => {
                    openList();
                    // A search field that leaves the old answer selected under the
                    // caret gets the first keystroke appended to it, which is
                    // never what was meant.
                    event.target.select();
                }}
                onKeyDown={onKeyDown}
                className={cn('pr-9', className)}
            />

            {/*
             * Decoration, not a control. The field opens on focus, so a button
             * here would be a second way in that does nothing the caret does not
             * already do, and a button inside the input row is a tab stop between
             * the label and the answer.
             */}
            <ChevronDown
                aria-hidden
                className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 opacity-50"
            />

            {open ? (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label={listLabel}
                    className="absolute top-[calc(100%+4px)] right-0 left-0 z-20 max-h-60 overflow-y-auto border border-sidebar-border bg-background"
                >
                    {matches.length === 0 ? (
                        <li className="px-3 py-2 text-sm text-secondary-foreground">
                            {emptyMessage}
                        </li>
                    ) : (
                        matches.map((option, index) => (
                            <li
                                key={option}
                                id={`${listboxId}-option-${index}`}
                                role="option"
                                aria-selected={option === value}
                                /*
                                 * Refusing the default focus move is what keeps the
                                 * input focused, and therefore keeps the click
                                 * alive long enough to choose.
                                 */
                                onMouseDown={(event) =>
                                    event.preventDefault()
                                }
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={() => choose(option)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm',
                                    index === activeIndex && 'bg-muted',
                                )}
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {option}
                                </span>
                                {option === value ? (
                                    <Check
                                        aria-hidden
                                        className="size-4 shrink-0"
                                    />
                                ) : null}
                            </li>
                        ))
                    )}
                </ul>
            ) : null}
        </div>
    );
};

export { Combobox };
