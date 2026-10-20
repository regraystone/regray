import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { DEFAULT_PASSWORD } from '../message/support/env';
import { ROOT } from '../message/support/stack';

/*
 * The accounts the inquiry suite signs in as. The e-mails come from
 * E2EInquirySeeder (and DemoAccountsSeeder for marketing and sales); every
 * one of them has UserFactory's password unless the environment says
 * otherwise.
 */

export type Role = 'client' | 'clientB' | 'marketing' | 'sales' | 'unverified';

export const ROLES: Role[] = [
    'client',
    'clientB',
    'marketing',
    'sales',
    'unverified',
];

/** What E2EInquirySeeder wrote to storage/app/e2e/inquiry-fixtures.json. */
export type InquiryFixtures = {
    users: Record<
        'clientA' | 'clientB' | 'unverified' | 'marketing' | 'sales',
        string
    >;
    clientAId: number;
    clientBId: number;
    retiredService: string;
    disk: string;
};

export const INQUIRY_FIXTURES_FILE = join(
    ROOT,
    'storage/app/e2e/inquiry-fixtures.json',
);

let cached: InquiryFixtures | null = null;

export const inquiryFixtures = (): InquiryFixtures => {
    cached ??= JSON.parse(
        readFileSync(INQUIRY_FIXTURES_FILE, 'utf8'),
    ) as InquiryFixtures;

    return cached;
};

const env = (name: string, fallback: string): string => {
    const value = process.env[name];

    return value === undefined || value === '' ? fallback : value;
};

/** The e-mail and password for `role`, e.g. E2E_EMAIL_CLIENT, E2E_PASSWORD_CLIENT. */
export const credentialsFor = (
    role: Role,
): { email: string; password: string } => {
    const { users } = inquiryFixtures();
    const seeded: Record<Role, string> = {
        client: users.clientA,
        clientB: users.clientB,
        marketing: users.marketing,
        sales: users.sales,
        unverified: users.unverified,
    };

    return {
        email: env(`E2E_EMAIL_${role.toUpperCase()}`, seeded[role]),
        password: env(`E2E_PASSWORD_${role.toUpperCase()}`, DEFAULT_PASSWORD),
    };
};

export const AUTH_DIR = join(ROOT, 'tests/e2e/.auth');

/** The saved sign-in for `role`, written by the global setup. */
export const storageStateFor = (role: Role): string => {
    const path = join(AUTH_DIR, `${role}.json`);

    if (!existsSync(path)) {
        throw new Error(
            `No saved sign-in for ${role}. The global setup writes ${path}.`,
        );
    }

    return path;
};
