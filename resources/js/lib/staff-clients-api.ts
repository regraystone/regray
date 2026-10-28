import { ApiError } from '@/lib/message-api';
import { readXsrfToken } from '@/lib/xsrf';
import { accounts as accountsRoute } from '@/routes/inquiry/inquire';
import { store as storeDirectClient } from '@/routes/inquiry/direct-clients';
import type { ContactKind } from '@/lib/staff-inquire';

/**
 * The staff Inquire flow's JSON endpoints: finding a client's account
 * (Platform) and taking a walk-in down (Direct). Refusals come back as the
 * Message page's ApiError, with the server's field errors.
 */

export type ClientAccount = { id: number; name: string; email: string };

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

export const createDirectClient = (
    input: DirectClientInput,
): Promise<DirectClientResult> =>
    request('POST', storeDirectClient.url(), input);
