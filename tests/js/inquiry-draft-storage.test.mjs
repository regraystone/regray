/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { beforeEach, describe, it } from 'node:test';

const memory = new Map();

globalThis.window = {
    localStorage: {
        getItem: (key) => memory.get(key) ?? null,
        setItem: (key, value) => memory.set(key, value),
        removeItem: (key) => memory.delete(key),
    },
};

const { inquiryDraftState, readInquiryDraft, writeInquiryDraft } =
    await import('../../resources/js/lib/inquiry-draft-storage.ts');

const USER = 7;
const KEY = `regray.inquiry-draft.v7:${USER}`;
const OLD_KEY = `regray.inquiry-draft.v4:${USER}`;

const draft = (overrides = {}) => ({
    name: '',
    stoneAdvice: false,
    stoneIds: [],
    stones: [],
    stoneWork: {},
    deliveryMethod: '',
    location: '',
    landmark: '',
    areaSqm: '',
    neededBy: '',
    notes: '',
    attachments: [],
    ...overrides,
});

const stateOf = (values, step = 2, projects = []) =>
    inquiryDraftState({
        step,
        editingProjectId: null,
        projects,
        draft: values,
    });

const entry = (overrides = {}) => ({
    serviceId: 'furniture-only',
    furnitureType: 'Tables',
    ...overrides,
});

/** A record as the previous version of this app wrote it. */
const versionFourRecord = () =>
    JSON.stringify({
        version: 4,
        savedAt: '2026-11-01T00:00:00.000Z',
        step: 4,
        editingProjectId: null,
        projects: [],
        draft: { ...draft(), serviceIds: ['supply-only'] },
        droppedAttachments: 0,
    });

const catalog = {
    stones: [{ slug: 'marble' }],
    services: [{ id: 'furniture-only' }],
};

describe('an old draft', () => {
    beforeEach(() => memory.clear());

    it('is dropped from its old key without an error', () => {
        memory.set(OLD_KEY, versionFourRecord());

        assert.equal(readInquiryDraft(USER), null);
        assert.equal(memory.has(OLD_KEY), false);
    });

    it('is dropped when a version 4 record sits under the current key', () => {
        memory.set(KEY, versionFourRecord());

        assert.equal(readInquiryDraft(USER), null);
        assert.equal(memory.has(KEY), false);
    });
});

describe('the work per stone in a stored draft', () => {
    beforeEach(() => memory.clear());

    it('comes back after a refresh', () => {
        const work = { marble: entry() };

        writeInquiryDraft(
            USER,
            stateOf(draft({ stoneIds: ['marble'], stoneWork: work })),
        );

        const stored = readInquiryDraft(USER);

        assert.deepEqual(stored.draft.stoneWork, {
            marble: {
                serviceId: 'furniture-only',
                furnitureType: 'Tables',
            },
        });
        assert.equal(stored.step, 2);
    });

    it('comes back with delivery or pickup', () => {
        writeInquiryDraft(USER, stateOf(draft({ deliveryMethod: 'pickup' })));

        assert.equal(readInquiryDraft(USER).draft.deliveryMethod, 'pickup');
    });

    it('keeps a draft of nothing but Help me choose', () => {
        writeInquiryDraft(USER, stateOf(draft({ stoneAdvice: true })));

        assert.equal(readInquiryDraft(USER).draft.stoneAdvice, true);
    });

    it('starts a draft saved with no delivery method on the default', () => {
        writeInquiryDraft(
            USER,
            stateOf(draft({ stoneIds: ['marble'], deliveryMethod: '' })),
        );

        assert.equal(readInquiryDraft(USER).draft.deliveryMethod, 'delivery');
    });

    it('leaves nothing behind for a stepper on its default delivery method', () => {
        writeInquiryDraft(USER, stateOf(draft({ deliveryMethod: 'delivery' })));

        assert.equal(memory.has(KEY), false);
    });

    it('still leaves nothing behind for a stepper that was only opened', () => {
        writeInquiryDraft(USER, stateOf(draft()));

        assert.equal(memory.has(KEY), false);
    });

    it('drops the work of a stone or an option no longer in the catalogue', () => {
        writeInquiryDraft(
            USER,
            stateOf(
                draft({
                    stoneIds: ['marble', 'gone'],
                    stoneWork: {
                        marble: entry(),
                        gone: entry(),
                        __recommended__: entry(),
                        stale: entry({ serviceId: 'retired' }),
                    },
                }),
            ),
        );

        assert.deepEqual(
            Object.keys(readInquiryDraft(USER, catalog).draft.stoneWork),
            ['marble', '__recommended__'],
        );
    });
});
