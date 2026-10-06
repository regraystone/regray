import { Search } from 'lucide-react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import ConversationCard from '@/components/conversation-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Conversation } from '@/data/conversations';
import { cn } from '@/lib/utils';

type ConversationFilter = 'all' | 'unread' | 'groups';

const FILTERS: Array<{ value: ConversationFilter; label: string }> = [
    { value: 'all', label: 'All' },
    { value: 'unread', label: 'Unread' },
    { value: 'groups', label: 'Groups' },
];

const matchesFilter = (
    conversation: Conversation,
    filter: ConversationFilter,
): boolean => {
    if (filter === 'unread') {
        return conversation.unread;
    }

    if (filter === 'groups') {
        return conversation.type === 'group';
    }

    return true;
};

const MIN_WIDTH_REM = 18;
const MAX_WIDTH_REM = 28;
const MIN_WIDTH = MIN_WIDTH_REM * 16;
const MAX_WIDTH = MAX_WIDTH_REM * 16;
const KEYBOARD_STEP = 16;

const clampWidth = (value: number): number =>
    Math.min(Math.max(Math.round(value), MIN_WIDTH), MAX_WIDTH);

type ConversationListProps = {
    conversations: Conversation[];
    selectedId: string | null;
    onSelect: (id: string) => void;
    className?: string;
};

const ConversationList = ({
    conversations,
    selectedId,
    onSelect,
    className,
}: ConversationListProps) => {
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<ConversationFilter>('all');
    const [width, setWidth] = useState<number | null>(null);
    const [isResizing, setIsResizing] = useState(false);
    const [measuredWidth, setMeasuredWidth] = useState(0);
    const navRef = useRef<HTMLElement>(null);
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

    const visibleConversations = useMemo(() => {
        const search = query.trim().toLowerCase();

        return conversations.filter(
            (conversation) =>
                matchesFilter(conversation, filter) &&
                (search === '' ||
                    conversation.name.toLowerCase().includes(search)),
        );
    }, [conversations, filter, query]);

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

        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        setWidth(clampWidth(drag.startWidth + (event.clientX - drag.startX)));
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
                <div className="relative">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-secondary-foreground"
                        aria-hidden
                    />
                    <Input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        aria-label="Search conversations"
                        placeholder="Search conversations"
                        className="h-11 rounded-none pr-3 pl-9"
                    />
                </div>

                <div
                    className="mt-3 flex [scrollbar-width:none] items-center gap-2.5 overflow-x-auto [&::-webkit-scrollbar]:hidden"
                    role="tablist"
                    aria-label="Filter conversations"
                >
                    {FILTERS.map((item) => {
                        const isActive = filter === item.value;

                        return (
                            <Button
                                key={item.value}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                variant={isActive ? 'default' : 'secondary'}
                                size="sm"
                                className="shrink-0 cursor-pointer px-3 py-1.5"
                                onClick={() => setFilter(item.value)}
                            >
                                {item.label}
                            </Button>
                        );
                    })}
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto">
                {visibleConversations.length === 0 ? (
                    <p className="px-4 py-16 text-center text-sm font-semibold text-secondary-foreground">
                        {conversations.length === 0
                            ? 'No conversations yet.'
                            : 'No conversations found.'}
                    </p>
                ) : (
                    visibleConversations.map((conversation) => (
                        <ConversationCard
                            key={conversation.id}
                            conversation={conversation}
                            selected={conversation.id === selectedId}
                            onSelect={onSelect}
                        />
                    ))
                )}
            </div>
        </nav>
    );
};

export default ConversationList;
