/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { profileCrumbs } from '../../resources/js/lib/profile-breadcrumbs.ts';

const urls = {
    profile: '/dashboard/profile',
    settings: '/dashboard/profile/settings',
    accountSecurity: '/dashboard/profile/account-security',
    security: '/dashboard/profile/account-security/security',
    schedule: '/dashboard/profile/schedule',
    scheduleBook: '/dashboard/profile/schedule/book',
    addresses: '/dashboard/profile/addresses',
};

const root = [{ label: 'Regray' }, { label: 'Dashboard' }];
const profile = { label: 'Profile', href: urls.profile };
const settings = { label: 'Settings', href: urls.settings };

describe('the profile breadcrumbs', () => {
    it('reads Regray / Dashboard / Profile on the Profile hub, with no link to itself', () => {
        assert.deepEqual(profileCrumbs(urls.profile, urls), [
            ...root,
            { label: 'Profile' },
        ]);
    });

    it('reads Regray / Dashboard / Profile / Settings on Settings', () => {
        assert.deepEqual(profileCrumbs(urls.settings, urls), [
            ...root,
            profile,
            { label: 'Settings' },
        ]);
    });

    it('nests Account & Security under Settings, though its URL is not', () => {
        assert.deepEqual(profileCrumbs(urls.accountSecurity, urls), [
            ...root,
            profile,
            settings,
            { label: 'Account & Security' },
        ]);
    });

    it('nests Security under Account & Security, each ancestor a link', () => {
        assert.deepEqual(profileCrumbs(urls.security, urls), [
            ...root,
            profile,
            settings,
            { label: 'Account & Security', href: urls.accountSecurity },
            { label: 'Security' },
        ]);
    });

    it('puts Addresses under Profile, not under Settings', () => {
        assert.deepEqual(profileCrumbs(urls.addresses, urls), [
            ...root,
            profile,
            { label: 'Addresses' },
        ]);
    });

    it('keeps the Schedule trails off Settings', () => {
        assert.deepEqual(profileCrumbs(urls.schedule, urls), [
            ...root,
            profile,
            { label: 'Schedule' },
        ]);
        assert.deepEqual(profileCrumbs(urls.scheduleBook, urls), [
            ...root,
            profile,
            { label: 'Schedule', href: urls.schedule },
            { label: 'Book' },
        ]);
    });

    it('reads Regray / Dashboard / Profile on a page under it with no trail of its own', () => {
        assert.deepEqual(
            profileCrumbs('/dashboard/profile/schedule/12/edit', urls),
            [...root, profile],
        );
    });

    it('has nothing to say off the Profile', () => {
        assert.equal(profileCrumbs('/dashboard/home', urls), null);
        assert.equal(profileCrumbs('/dashboard/profiles', urls), null);
    });
});
