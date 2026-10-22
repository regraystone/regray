/*
 * The Message page's data, exactly as the server sends it (see
 * app/Http/Resources/Message). Keys are camelCase, like every other resource.
 */

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
};

export type InquiryStatus =
    | 'draft'
    | 'pending'
    | 'quoted'
    | 'paid'
    | 'completed'
    | 'cancelled';

export type InquiryCard = {
    reference: string;
    projectCount: number;
    serviceCount: number;
    stones: Array<{ slug: string; name: string; thumbnail: string }>;
    stoneCount: number;
    announcedStatus: InquiryStatus;
    status: InquiryStatus;
    occurredAt: string;
};

export type ScheduleStatus =
    | 'pending'
    | 'confirmed'
    | 'completed'
    | 'cancelled';

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
    type: 'text' | 'inquiry_card' | 'schedule_card' | 'system';
    body: string | null;
    isRemoved: boolean;
    isEdited: boolean;
    createdAt: string;
    clientKey: string | null;
    sender: { id: number; name: string } | null;
    replyTo: {
        id: number;
        senderName: string | null;
        snippet: string | null;
        isRemoved: boolean;
    } | null;
    inquiryCard: InquiryCard | null;
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
    canRelease: boolean;
    canReassign: boolean;
};

export type ThreadPayload = {
    conversation: ConversationSummary;
    messages: ChatMessage[];
    hasMore: boolean;
    pointers: Pointer[];
    assignment: Assignment | null;
};

export type MessagePageProps = {
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
};

export type Realtime = {
    key: string;
    host: string | null;
    port: number | null;
    scheme: string | null;
} | null;
