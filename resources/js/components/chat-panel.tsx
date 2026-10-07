import { ArrowLeft, Info, Paperclip, Phone, Send } from 'lucide-react';
import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import InquiryThreadCard from '@/components/inquiry-thread-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { currentUserId, type Conversation } from '@/data/conversations';
import { inquiries } from '@/data/inquiries';
import { useInitials } from '@/hooks/use-initials';
import { getPerson } from '@/lib/people';
import { cn } from '@/lib/utils';

type ChatPanelProps = {
    conversation: Conversation;
    onBack: () => void;
    onSend: (text: string) => void;
    className?: string;
};

const ChatPanel = ({
    conversation,
    onBack,
    onSend,
    className,
}: ChatPanelProps) => {
    const getInitials = useInitials();
    const [draft, setDraft] = useState('');
    const scrollRef = useRef<HTMLDivElement>(null);
    const messageCount = conversation.messages.length;
    const initials = getInitials(conversation.name);
    const inquiry = conversation.inquiryId
        ? inquiries.find((item) => item.id === conversation.inquiryId)
        : undefined;

    useEffect(() => {
        setDraft('');
    }, [conversation.id]);

    useEffect(() => {
        const viewport = scrollRef.current;

        if (viewport) {
            viewport.scrollTop = viewport.scrollHeight;
        }
    }, [conversation.id, messageCount]);

    const handleSend = () => {
        const text = draft.trim();

        if (!text) {
            return;
        }

        onSend(text);
        setDraft('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleSend();
        }
    };

    return (
        <section
            aria-label={`Conversation with ${conversation.name}`}
            className={cn(
                'flex min-w-0 flex-1 flex-col overflow-hidden',
                className,
            )}
        >
            <header className="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border px-4 sm:h-16">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={onBack}
                    aria-label="Back to conversations"
                    className="h-11 w-11 shrink-0 cursor-pointer md:hidden"
                >
                    <ArrowLeft className="size-5" aria-hidden />
                </Button>
                <Avatar className="h-9 w-9 border border-border sm:h-10 sm:w-10">
                    {conversation.avatar ? (
                        <AvatarImage
                            src={conversation.avatar}
                            alt={conversation.name}
                        />
                    ) : null}
                    <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground sm:text-sm">
                        {initials}
                    </AvatarFallback>
                </Avatar>
                <h2 className="min-w-0 truncate text-sm font-bold sm:text-base">
                    {conversation.name}
                </h2>
                <div className="ml-auto flex shrink-0 items-center gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Start a call"
                        className="cursor-pointer"
                    >
                        <Phone className="size-5" aria-hidden />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="View conversation info"
                        className="cursor-pointer"
                    >
                        <Info className="size-5" aria-hidden />
                    </Button>
                </div>
            </header>

            <div
                ref={scrollRef}
                className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 md:p-5"
            >
                {/* The card is part of the thread, so it scrolls with it and is
                 * the first thing read when the conversation is opened from the
                 * bottom. It is derived from the inquiry, never a message, so
                 * `messageCount` below still counts real messages only. */}
                {inquiry ? <InquiryThreadCard inquiry={inquiry} /> : null}

                {messageCount === 0 ? (
                    <p
                        className={cn(
                            'px-4 text-center text-sm font-semibold text-secondary-foreground',
                            // Centred only while the card is absent. With the card
                            // above it, `m-auto` would push the hint to the middle
                            // of the empty space and tear the two apart.
                            inquiry ? '' : 'm-auto',
                        )}
                    >
                        No messages yet — say hello
                    </p>
                ) : (
                    conversation.messages.map((message, index) => {
                        const isOwn = message.senderId === currentUserId;
                        const sender = isOwn
                            ? undefined
                            : getPerson(message.senderId);
                        const previous = conversation.messages[index - 1];
                        const next = conversation.messages[index + 1];
                        const isRunStart =
                            !previous || previous.senderId !== message.senderId;
                        const isRunEnd =
                            !next || next.senderId !== message.senderId;

                        return (
                            <div
                                key={message.id}
                                className="grid grid-cols-[2rem_1fr] items-end gap-x-2"
                            >
                                <div className="size-8 shrink-0">
                                    {sender && isRunEnd ? (
                                        <Avatar className="size-8 border border-border">
                                            {sender.avatar ? (
                                                <AvatarImage
                                                    src={sender.avatar}
                                                    alt={sender.name}
                                                />
                                            ) : null}
                                            <AvatarFallback className="bg-primary text-xs font-bold text-primary-foreground">
                                                {getInitials(sender.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                    ) : null}
                                </div>

                                <div
                                    className={cn(
                                        'flex min-w-0 flex-col',
                                        isOwn ? 'items-end' : 'items-start',
                                    )}
                                >
                                    {sender &&
                                    conversation.type === 'group' &&
                                    isRunStart ? (
                                        <span className="mb-1 text-xs font-bold text-secondary-foreground">
                                            {sender.name}
                                        </span>
                                    ) : null}
                                    <p
                                        className={cn(
                                            'max-w-[80%] px-3 py-2 text-sm break-words md:max-w-[65%]',
                                            isOwn
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-secondary text-foreground',
                                        )}
                                    >
                                        {message.text}
                                    </p>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            <div className="flex shrink-0 items-center gap-2 border-t border-sidebar-border p-3">
                <Button
                    type="button"
                    variant="secondary"
                    size="icon"
                    aria-label="Attach a file"
                    className="size-11 shrink-0 cursor-pointer"
                >
                    <Paperclip className="size-4" aria-hidden />
                </Button>
                <Input
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={handleKeyDown}
                    aria-label={`Message ${conversation.name}`}
                    placeholder={`Message ${conversation.name}`}
                    className="h-11 flex-1 rounded-none"
                />
                <Button
                    type="button"
                    size="icon"
                    onClick={handleSend}
                    aria-label="Send message"
                    className="size-11 shrink-0 cursor-pointer"
                >
                    <Send className="size-4" aria-hidden />
                </Button>
            </div>
        </section>
    );
};

export default ChatPanel;
