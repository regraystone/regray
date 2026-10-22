/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { canPickService } from '../../resources/js/lib/inquiry-catalog.ts';

const services = [
    { id: 'supply', group: 'stone-work' },
    { id: 'fabrication', group: 'stone-work' },
    { id: 'relocation', group: 'stone-relocation' },
];

describe('canPickService', () => {
    it('allows any service when nothing is picked', () => {
        assert.equal(canPickService(services, [], 'relocation'), true);
    });

    it('allows a service from the group already picked', () => {
        assert.equal(canPickService(services, ['supply'], 'fabrication'), true);
    });

    it('blocks a service from another group, both ways', () => {
        assert.equal(canPickService(services, ['supply'], 'relocation'), false);
        assert.equal(canPickService(services, ['relocation'], 'supply'), false);
    });

    it('always lets a picked service be unpicked', () => {
        assert.equal(
            canPickService(services, ['relocation'], 'relocation'),
            true,
        );
    });
});
