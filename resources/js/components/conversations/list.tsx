import { InfiniteScroll } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import {
    useEffect,
    useRef,
    useState,
    type CSSProperties,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import ConversationRow from '@/components/conversations/row';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    conversationListSkeleton,
    SKELETON_ROW_COUNT,
} from '@/lib/conversation-skeleton';
import { cn } from '@/lib/utils';
import type { ConversationSummary } from '@/types/message';

type ConversationFilter = 'all' | 'unread';

const FILTERS: Array<{ value: ConversationFilter; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
];

const SEARCH_DEBOUNCE_MS = 200;
const MIN_WIDTH = 18 * 16;
const MAX_WIDTH = 28 * 16;
const KEYBOARD_STEP = 16;

const clampWidth = (value: number): number =>
    Math.min(Math.max(Math.round(value), MIN_WIDTH), MAX_WIDTH);

/** Placeholder rows shaped like real ones, announced once. */
export const ConversationRowSkeletons = ({
    label = 'Loading conversations',
}: {
    label?: string;
}) => (
    <div role="status" aria-busy="true" data-testid="conversation-skeleton">
        <span className="sr-only">{label}</span>
        {Array.from({ length: SKELETON_ROW_COUNT }, (_, index) => (
            <div
                key={index}
                className="flex items-center gap-3 border-b border-sidebar-border px-4 py-3"
                data-testid="conversation-skeleton-row"
            >
                <Skeleton className="size-12 shrink-0 rounded-full" />
                <div className="min-w-0 flex-1 space-y-2">
                    <Skeleton className="h-4 w-1/2 rounded-none" />
                    <Skeleton className="h-4 w-3/4 rounded-none" />
                </div>
            </div>
        ))}
    </div>
);

type ConversationListProps = {
    conversations: ConversationSummary[];
    selectedId: number | null;
    onSelect: (conversation: ConversationSummary) => void;
    filters: { search: string; filter: ConversationFilter };
    onFiltersChange: (filters: {
        search: string;
        filter: ConversationFilter;
    }) => void;
    isUpdating: boolean;
    canCreateGroup: boolean;
    onNewGroup: () => void;
    className?: string;
};

/**
 * The conversation list: search and the All/Unread filter run on the server,
 * pages load as the list is scrolled, and the width can be dragged.
 */
const ConversationList = ({
    conversations,
    selectedId,
    onSelect,
    filters,
    onFiltersChange,
    isUpdating,
    canCreateGroup,
    onNewGroup,
    className,
}: ConversationListProps) => {
    const [query, setQuery] = useState(filters.search);
    const [width, setWidth] = useState<number | null>(null);
    const [isResizing, setIsResizing] = useState(false);
    const [measuredWidth, setMeasuredWidth] = useState(0);
    const navRef = useRef<HTMLElement>(null);
    const debounceRef = useRef<number | null>(null);
    const dragRef = useRef<{
        pointerId: number;
        startX: number;
        startWidth: number;
    } | null>(null);

    useEffect(() => {
        const nav = navRef.current;

        if (!nav) {
            return;
        }

        const observer = new ResizeObserver((entries) => {
            const entry = entries[0];

            if (entry) {
                setMeasuredWidth(entry.contentRect.width);
            }
        });

        observer.observe(nav);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!isResizing) {
            return;
        }

        const previous = document.body.style.userSelect;
        document.body.style.userSelect = 'none';

        return () => {
            document.body.style.userSelect = previous;
        };
    }, [isResizing]);

    useEffect(
        () => () => {
            if (debounceRef.current !== null) {
                window.clearTimeout(debounceRef.current);
            }
        },
        [],
    );

    const handleSearch = (value: string) => {
        setQuery(value);

        if (debounceRef.current !== null) {
            window.clearTimeout(debounceRef.current);
        }

        debounceRef.current = window.setTimeout(
            () => onFiltersChange({ search: value, filter: filters.filter }),
            SEARCH_DEBOUNCE_MS,
        );
    };

    const handleResizeStart = (event: ReactPointerEvent<HTMLDivElement>) => {
        const nav = navRef.current;

        if (!nav) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        dragRef.current = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startWidth: nav.getBoundingClientRect().width,
        };
        setIsResizing(true);
    };

    const handleResizeMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const drag = dragRef.current;

        if (drag && drag.pointerId === event.pointerId) {
            setWidth(
                clampWidth(drag.startWidth + (event.clientX - drag.startX)),
            );
        }
    };

    const handleResizeEnd = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (dragRef.current?.pointerId === event.pointerId) {
            dragRef.current = null;
        }

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }

        setIsResizing(false);
    };

    const handleResizeKeyDown = (event: ReactKeyboardEvent<HTMLDivElement>) => {
        const nav = navRef.current;

        if (!nav) {
            return;
        }

        const current = width ?? nav.getBoundingClientRect().width;
        const step = event.shiftKey ? KEYBOARD_STEP * 4 : KEYBOARD_STEP;

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            setWidth(clampWidth(current - step));
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            setWidth(clampWidth(current + step));
        } else if (event.key === 'Home') {
            event.preventDefault();
            setWidth(null);
        }
    };

    const currentWidth = Math.round(width ?? measuredWidth);
    const skeleton = conversationListSkeleton({
        isFetching: isUpdating,
        hasRows: conversations.length > 0,
        mode: 'filter',
    });
    const isFiltered = filters.search !== '' || filters.filter !== 'all';

    return (
        <nav
            ref={navRef}
            aria-label="Conversations"
            className={cn(
                'relative flex w-full min-w-0 flex-col md:shrink-0 md:border-r md:border-sidebar-border',
                width === null
                    ? 'md:w-80 lg:w-96'
                    : 'md:w-[var(--list-w)] lg:w-[var(--list-w)]',
                className,
            )}
            style={
                width === null
                    ? undefined
                    : ({ '--list-w': `${width}px` } as CSSProperties)
            }
        >
            <div
                role="separator"
                aria-orientation="vertical"
                aria-label="Resize conversation list"
                aria-valuemin={MIN_WIDTH}
                aria-valuemax={MAX_WIDTH}
                aria-valuenow={currentWidth}
                aria-valuetext={`${currentWidth} pixels`}
                tabIndex={0}
                onPointerDown={handleResizeStart}
                onPointerMove={handleResizeMove}
                onPointerUp={handleResizeEnd}
                onPointerCancel={handleResizeEnd}
                onKeyDown={handleResizeKeyDown}
                onDoubleClick={() => setWidth(null)}
                className="group absolute inset-y-0 -right-2 z-50 hidden w-4 cursor-col-resize touch-none select-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none md:flex md:items-center md:justify-center"
            >
                <span
                    className="pointer-events-none flex items-center gap-0.5"
                    aria-hidden
                >
                    {Array.from({ length: 3 }, (_, index) => (
                        <span
                            key={index}
                            className="h-4 w-0.5 bg-secondary-foreground/70 transition-colors group-hover:bg-secondary-foreground group-focus-visible:bg-secondary-foreground"
                        />
                    ))}
                </span>
            </div>

            <div className="shrink-0 border-b border-sidebar-border p-3">
                <div className="flex items-center gap-2">
                    <div className="relative min-w-0 flex-1">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                            aria-hidden
                        />
                        <Input
                            type="search"
                            value={query}
                            onChange={(event) =>
                                handleSearch(event.target.value)
                            }
                            aria-label="Search conversations"
                            placeholder="Search conversations"
                            className="h-11 rounded-none pr-9 pl-9 [&::-webkit-search-cancel-button]:appearance-none"
                        />
                        {query ? (
                            <button
                                type="button"
                                onClick={() => handleSearch('')}
                                aria-label="Clear search"
                                className="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 cursor-pointer items-center justify-center rounded-md text-secondary-foreground hover:opacity-70"
                            >
                                <X className="size-4" aria-hidden />
                            </button>
                        ) : null}
                    </div>
                    {canCreateGroup ? (
                        <Button
                            type="button"
                            size="sm"
                            onClick={onNewGroup}
                            className="h-11 shrink-0 rounded-md"
                        >
                            <Plus aria-hidden />
                            New group
                        </Button>
                    ) : null}
                </div>

                <div
                    className="mt-3 flex items-center gap-2.5"
                    role="tablist"
                    aria-label="Filter conversations"
                >
                    {FILTERS.map((item) => {
                        const isActive = filters.filter === item.value;

                        return (
                            <Button
                                key={item.value}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                variant={isActive ? 'default' : 'secondary'}
                                size="sm"
                                className="shrink-0 cursor-pointer rounded-md px-3 py-1.5"
                                onClick={() =>
                                    onFiltersChange({
                                        search: query,
                                        filter: item.value,
                                    })
                                }
                            >
                                {item.label}
                            </Button>
                        );
                    })}
                </div>
            </div>

            <div
                className="min-h-0 flex-1 overflow-y-auto"
                data-testid="conversation-list"
            >
                {conversations.length === 0 ? (
                    skeleton.rows ? (
                        <ConversationRowSkeletons />
                    ) : (
                        <p className="px-4 py-16 text-center text-sm font-semibold text-secondary-foreground">
                            {isFiltered
                                ? 'No conversations found.'
                                : 'No conversations yet.'}
                        </p>
                    )
                ) : (
                    <InfiniteScroll
                        data="conversations"
                        buffer={200}
                        next={({ loading }) =>
                            conversationListSkeleton({
                                isFetching: loading,
                                hasRows: true,
                                mode: 'loadMore',
                            }).footer ? (
                                <ConversationRowSkeletons label="Loading more conversations" />
                            ) : null
                        }
                    >
                        <div
                            aria-busy={skeleton.dim}
                            aria-live="polite"
                            className={cn(
                                'transition-opacity',
                                skeleton.dim && 'opacity-60',
                            )}
                        >
                            {conversations.map((conversation) => (
                                <ConversationRow
                                    key={conversation.id}
                                    conversation={conversation}
                                    selected={conversation.id === selectedId}
                                    onSelect={onSelect}
                                />
                            ))}
                        </div>
                    </InfiniteScroll>
                )}
            </div>
        </nav>
    );
};

export default ConversationList;
