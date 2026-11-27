/*
 * The Inbox page's data, exactly as the server sends it (see
 * app/Http/Resources/Message). Keys are camelCase, like every other resource.
 */

import type {
    InquiryStatus,
    PaymentStatus,
    ProcessStatus,
    SalesStatus,
} from '@/lib/inquiry-statuses';

export type ConversationType = 'direct' | 'group' | 'support';

/** A conversation as a list row and as a thread header. */
export type ConversationSummary = {
    id: number;
    type: ConversationType;
    name: string;
    avatar: string | null;
    isSupport: boolean;
    /** The viewer's own Regray Team thread (clients only). */
    isOwnSupport: boolean;
    unreadCount: number;
    lastActivityAt: string;
    preview: string;
    /** Who handles a Regray Team thread; null when nobody does yet. */
    assignee: { id: number; name: string } | null;
    /** The client a Regray Team thread belongs to (staff only). */
    supportClient: { id: number; name: string } | null;
    canClaim: boolean;
    memberCount: number | null;
    /** Whether the reader may add accounting or logistics: a sales member of an inquiry's group chat. */
    canAddMembers: boolean;
    /**
     * Whether the reader may write here at all. Only on the open thread's
     * summary; a list row leaves it out.
     */
    canSend?: boolean;
};

export type { InquiryStatus, PaymentStatus, ProcessStatus, SalesStatus };

/**
 * An inquiry as the `inquiry_card` message announced it: the project it is,
 * and the snapshot the card was posted for. `status` is the inquiry's display
 * status now, which can have moved on since; null for a viewer who may not view
 * the inquiry (a support-pool member it is not visible to).
 */
export type InquiryCard = {
    reference: string;
    name: string;
    /** Null on a pickup. */
    location: string | null;
    stones: Array<{ slug: string; name: string; thumbnail: string }>;
    stoneCount: number;
    /** The client asked Regray to recommend a stone instead of choosing one. */
    stoneAdviceRequested: boolean;
    /** The one service it asks for; null only for an inquiry without one. */
    service: { id: string; name: string } | null;
    announcedStatus: InquiryStatus;
    /** Null for a stage that had not started when the card was posted. */
    announcedSalesStatus: SalesStatus | null;
    announcedPaymentStatus: PaymentStatus | null;
    announcedProcessStatus: ProcessStatus | null;
    status: InquiryStatus | null;
    occurredAt: string;
};

/**
 * A quotation Sales sent, as the `quotation_card` message posted it: the
 * document and its inquiry as they were then. Both links are the inquiry's
 * file route, which answers 404 to a reader who may not see the document.
 */
export type QuotationCard = {
    fileName: string;
    sizeBytes: number;
    /** Null only for a quotation stored before revisions were counted. */
    revision: number | null;
    /** The inquiry's stones, each once; 0 for a service-only inquiry. */
    materialCount: number;
    inquiryName: string;
    reference: string;
    viewUrl: string;
    downloadUrl: string;
    occurredAt: string;
};

/** What a schedule card announces: a visit's status, or that it moved. */
export type ScheduleStatus =
    | 'pending'
    | 'confirmed'
    | 'rescheduled'
    | 'served'
    | 'cancelled'
    | 'expired';

export type BookedByType = 'client' | 'staff';

export type ScheduleCard = {
    id: number;
    title: string;
    arrivalAt: string;
    announcedStatus: ScheduleStatus;
    bookedByType: BookedByType;
    bookedByName: string | null;
    inquiryReference: string | null;
    cancelReason: string | null;
    /** Why it was moved, on a rescheduled card. */
    changeReason: string | null;
    occurredAt: string;
};

export type SystemEventName = 'assigned' | 'released' | 'reassigned';

/** A file sent with a message. `url` is the authorised download route. */
export type ChatAttachment = {
    id: number;
    name: string;
    mimeType: string;
    size: number;
    isImage: boolean;
    url: string;
};

export type ChatMessage = {
    id: number;
    conversationId: number;
    type:
        | 'text'
        | 'inquiry_card'
        | 'quotation_card'
        | 'schedule_card'
        | 'system';
    body: string | null;
    isRemoved: boolean;
    isEdited: boolean;
    createdAt: string;
    clientKey: string | null;
    /** `avatar`: a client sender's profile photo, or null to show initials. */
    sender: { id: number; name: string; avatar?: string | null } | null;
    replyTo: {
        id: number;
        senderName: string | null;
        snippet: string | null;
        isRemoved: boolean;
    } | null;
    inquiryCard: InquiryCard | null;
    quotationCard: QuotationCard | null;
    scheduleCard: ScheduleCard | null;
    /** Empty for a removed message and for everything but text. */
    attachments: ChatAttachment[];
    systemEvent: { event: SystemEventName; userName: string | null } | null;
};

export type Pointer = {
    userId: number;
    name: string;
    lastReadMessageId: number | null;
    lastDeliveredMessageId: number | null;
};

/** Staff-only state of a Regray Team thread; null for clients and groups. */
export type Assignment = {
    canClaim: boolean;
    assignee: { id: number; name: string } | null;
    isMine: boolean;
};

export type ThreadPayload = {
    conversation: ConversationSummary;
    messages: ChatMessage[];
    hasMore: boolean;
    pointers: Pointer[];
    assignment: Assignment | null;
    /** The server's answer: this viewer's Regray Team thread, and no inquiry going. */
    canRequestQuote: boolean;
};

export type InboxPageProps = {
    /** Absent only before the first response has arrived. */
    conversations?: { data: ConversationSummary[] };
    filters: { search: string; filter: 'all' | 'unread' };
    activeConversation: ConversationSummary | null;
    currentUserId: number;
    isClient: boolean;
    canCreateGroup: boolean;
};

/** A message the viewer sent that the server has not confirmed yet. */
export type PendingMessage = {
    clientKey: string;
    body: string;
    /** Kept so a retry can send them again. */
    files: File[];
    replyTo: ChatMessage['replyTo'];
    createdAt: string;
    state: 'sending' | 'failed';
    /** The server's reason when it refused the send (a 409 says who handles it). */
    error?: string;
    /** When a 429 lets the send be retried (a Date.now() timestamp). */
    retryAt?: number | null;
};

export type Realtime = {
    key: string;
    host: string | null;
    port: number | null;
    scheme: string | null;
} | null;
