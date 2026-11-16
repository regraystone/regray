import { ApiError } from '@/lib/message-api';
import { readXsrfToken } from '@/lib/xsrf';
import { accounts as accountsRoute } from '@/routes/inquiry/inquire';
import { store as storeDirectClient } from '@/routes/inquiry/direct-clients';
import { accounts as bookingAccountsRoute } from '@/routes/schedule/book';
import type { ContactKind } from '@/lib/staff-inquire';

/**
 * The staff Inquire flow's JSON endpoints: finding a client's account
 * (Platform) and taking a walk-in down (Direct). Refusals come back as the
 * Inbox page's ApiError, with the server's field errors.
 */

/**
 * A client account the user is authorized for (the server scopes the
 * search). `label` is what a result is shown by: the name, or the email when
 * the search is an email address this account's matches.
 */
export type ClientAccount = {
    id: number;
    name: string;
    email: string;
    label: string;
};

export type DirectClient = {
    id: number;
    fullName: string;
    company: string | null;
    kind: 'platform' | 'direct';
};

export type DirectClientInput = {
    fullName: string;
    company: string;
    address: string;
    contacts: Array<{ kind: ContactKind; value: string }>;
};

/**
 * `existing` is true when one of the contacts was already another client's:
 * nothing was created, and that client is offered instead.
 */
export type DirectClientResult = {
    client: DirectClient;
    existing: boolean;
    matched: { kind: ContactKind; value: string } | null;
};

const request = async <T>(
    method: 'GET' | 'POST',
    url: string,
    body?: unknown,
): Promise<T> => {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
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

export const searchClientAccounts = (
    search: string,
): Promise<{ accounts: ClientAccount[] }> =>
    request(
        'GET',
        accountsRoute.url({
            query: search.trim() === '' ? {} : { search },
        }),
    );

/**
 * The same search, for staff booking an appointment: its own route, guarded
 * by the schedule permissions, so a staff role that books but opens no
 * inquiries can still find the client.
 */
export const searchBookingClientAccounts = (
    search: string,
): Promise<{ accounts: ClientAccount[] }> =>
    request(
        'GET',
        bookingAccountsRoute.url({
            query: search.trim() === '' ? {} : { search },
        }),
    );

export const createDirectClient = (
    input: DirectClientInput,
): Promise<DirectClientResult> =>
    request('POST', storeDirectClient.url(), input);
