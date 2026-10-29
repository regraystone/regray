import { currentSocketId } from '@/lib/echo';
import { rateLimitFrom } from '@/lib/rate-limit';
import { readXsrfToken } from '@/lib/xsrf';
import { store as storeGroup } from '@/routes/message/groups';
import {
    index as membersRoute,
    store as storeMembersRoute,
} from '@/routes/message/members';
import {
    claim as claimRoute,
    delivered as deliveredRoute,
    people as peopleRoute,
    read as readRoute,
    reassign as reassignRoute,
    release as releaseRoute,
    send as sendRoute,
    thread as threadRoute,
} from '@/routes/message';
import {
    destroy as destroyMessage,
    update as updateMessage,
} from '@/routes/message/messages';
import type {
    Assignment,
    ChatMessage,
    ConversationSummary,
    Pointer,
    ThreadPayload,
} from '@/types/message';

/**
 * The Message page's JSON endpoints. Inertia drives the page itself; these are
 * the requests a thread makes while it is open.
 */

/**
 * A request the server refused, with the status and the server's words. A 429
 * says how long to wait instead, from its Retry-After header, and `retryAt`
 * is when a retry may go (a Date.now() timestamp).
 */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
        public readonly retryAt: number | null = null,
    ) {
        super(message);
    }
}

/** JSON, or FormData when files go with it (the browser sets that type). */
const request = async <T>(
    method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
    url: string,
    body?: unknown,
): Promise<T> => {
    const socketId = currentSocketId();
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
            ...(body === undefined || isForm
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(socketId ? { 'X-Socket-ID': socketId } : {}),
        },
        ...(body === undefined
            ? {}
            : { body: isForm ? body : JSON.stringify(body) }),
    });

    if (!response.ok) {
        const data = (await response.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        const rateLimit = rateLimitFrom({
            status: response.status,
            headers: {
                'retry-after': response.headers.get('Retry-After') ?? '',
            },
        });

        throw new ApiError(
            response.status,
            rateLimit?.message ??
                data.message ??
                'Something went wrong. Please try again.',
            data.errors ?? {},
            rateLimit?.retryAt ?? null,
        );
    }

    return (await response.json()) as T;
};

export const fetchThread = (
    conversationId: number,
    query: { before?: number; after?: number; limit?: number } = {},
): Promise<ThreadPayload> =>
    request<ThreadPayload>(
        'GET',
        threadRoute.url(conversationId, {
            query: Object.fromEntries(
                Object.entries(query).filter(
                    ([, value]) => value !== undefined,
                ),
            ),
        }),
    );

export const sendMessage = (
    conversationId: number,
    payload: { body: string; replyToId: number | null; clientKey: string },
    files: File[] = [],
): Promise<{ message: ChatMessage }> => {
    if (files.length === 0) {
        return request('POST', sendRoute.url(conversationId), payload);
    }

    const form = new FormData();
    form.append('body', payload.body);
    form.append('clientKey', payload.clientKey);

    if (payload.replyToId !== null) {
        form.append('replyToId', String(payload.replyToId));
    }

    files.forEach((file) => form.append('attachments[]', file));

    return request('POST', sendRoute.url(conversationId), form);
};

export const editMessage = (
    messageId: number,
    body: string,
): Promise<{ message: ChatMessage }> =>
    request('PATCH', updateMessage.url(messageId), { body });

export const removeMessage = (
    messageId: number,
): Promise<{ message: ChatMessage }> =>
    request('DELETE', destroyMessage.url(messageId));

export const markRead = (
    conversationId: number,
    lastReadMessageId: number,
): Promise<{ pointers: Pointer[] }> =>
    request('POST', readRoute.url(conversationId), { lastReadMessageId });

export const markDelivered = (
    conversationId: number,
    lastDeliveredMessageId: number,
): Promise<{ pointers: Pointer[] }> =>
    request('POST', deliveredRoute.url(conversationId), {
        lastDeliveredMessageId,
    });

type AssignmentResponse = {
    conversation: ConversationSummary;
    assignment: Assignment | null;
};

export const claimConversation = (
    conversationId: number,
): Promise<AssignmentResponse> =>
    request('POST', claimRoute.url(conversationId));

export const releaseConversation = (
    conversationId: number,
): Promise<AssignmentResponse> =>
    request('POST', releaseRoute.url(conversationId));

export const reassignConversation = (
    conversationId: number,
    userId: number,
): Promise<AssignmentResponse> =>
    request('POST', reassignRoute.url(conversationId), { userId });

export type Person = { id: number; name: string };

/** One of the chosen client's inquiries, as a group can be bound to it. */
export type GroupInquiryOption = {
    id: number;
    reference: string;
    name: string;
    /** It has a group chat already, and an inquiry has at most one. */
    hasGroup: boolean;
};

export type GroupPeople = {
    /** The staff the reader may pick: sales and technical for marketing. */
    staff: Person[];
    clients: Person[];
    /** Every owner, who is always added. */
    owners: Person[];
    /** The chosen client's inquiries; empty until a client is chosen. */
    inquiries: GroupInquiryOption[];
};

export const searchPeople = (
    search: string,
    clientId: number | null = null,
): Promise<GroupPeople> =>
    request(
        'GET',
        peopleRoute.url({
            query: {
                ...(search.trim() === '' ? {} : { search }),
                ...(clientId === null ? {} : { clientId: String(clientId) }),
            },
        }),
    );

export const createGroup = (payload: {
    name: string;
    staffIds: number[];
    clientId: number;
    inquiryId: number;
}): Promise<{ conversation: ConversationSummary }> =>
    request('POST', storeGroup.url(), payload);

/** Accounting and logistics users not in the group yet, for a sales member to add. */
export const searchAddableMembers = (
    conversationId: number,
    search: string,
): Promise<{ people: Person[] }> =>
    request(
        'GET',
        membersRoute.url(conversationId, {
            query: search.trim() === '' ? {} : { search },
        }),
    );

export const addGroupMembers = (
    conversationId: number,
    userIds: number[],
): Promise<{ conversation: ConversationSummary }> =>
    request('POST', storeMembersRoute.url(conversationId), { userIds });

/** A random id for a send, so a retry is recognised as the same send. */
export const newClientKey = (): string => {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        try {
            return crypto.randomUUID();
        } catch {
            // Not a secure context: fall through to the manual version.
        }
    }

    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0'));

    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`;
};
