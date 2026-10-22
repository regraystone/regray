import { currentSocketId } from '@/lib/echo';
import { readXsrfToken } from '@/lib/xsrf';
import { store as storeGroup } from '@/routes/message/groups';
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

/** A request the server refused, with the status and the server's words. */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
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

        throw new ApiError(
            response.status,
            data.message ?? 'Something went wrong. Please try again.',
            data.errors ?? {},
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

export const searchPeople = (
    search: string,
): Promise<{ staff: Person[]; clients: Person[] }> =>
    request(
        'GET',
        peopleRoute.url({ query: search.trim() === '' ? {} : { search } }),
    );

export const createGroup = (payload: {
    name: string;
    staffIds: number[];
    clientId: number;
}): Promise<{ conversation: ConversationSummary }> =>
    request('POST', storeGroup.url(), payload);

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
