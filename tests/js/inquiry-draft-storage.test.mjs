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

const {
    draftStoneSlugs,
    inquiryDraftState,
    keepKnownStones,
    readInquiryDraft,
    writeInquiryDraft,
} = await import('../../resources/js/lib/inquiry-draft-storage.ts');

const USER = 7;
const KEY = `regray.inquiry-draft.v9:${USER}`;
const OLD_KEY = `regray.inquiry-draft.v8:${USER}`;

const draft = (overrides = {}) => ({
    name: '',
    serviceTypeId: '',
    serviceId: '',
    stoneAdvice: false,
    stoneIds: [],
    stones: [],
    deliveryMethod: '',
    location: '',
    answers: {},
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

/** A record as the previous version of this app wrote it, with a landmark and an area. */
const versionEightRecord = () =>
    JSON.stringify({
        version: 8,
        savedAt: '2026-11-01T00:00:00.000Z',
        step: 2,
        editingProjectId: null,
        projects: [],
        draft: {
            ...draft(),
            answers: undefined,
            stoneIds: ['marble'],
            landmark: 'Near the church',
            areaSqm: '12.5',
        },
        droppedAttachments: 0,
    });

const catalog = {
    stones: [],
    serviceTypes: [
        {
            id: 'custom-stone-furniture',
            name: 'Custom Stone Furniture',
            services: [{ id: 'dining-tables', name: 'Dining Tables' }],
        },
    ],
};

describe('an old draft', () => {
    beforeEach(() => memory.clear());

    it('is dropped from its old key without an error', () => {
        memory.set(OLD_KEY, versionEightRecord());

        assert.equal(readInquiryDraft(USER), null);
        assert.equal(memory.has(OLD_KEY), false);
    });

    it('is dropped when a version 8 record sits under the current key', () => {
        memory.set(KEY, versionEightRecord());

        assert.equal(readInquiryDraft(USER), null);
        assert.equal(memory.has(KEY), false);
    });
});

describe('a stored draft', () => {
    beforeEach(() => memory.clear());

    it('reads a stone row saved before thickness was asked as unanswered', () => {
        writeInquiryDraft(
            USER,
            stateOf(
                draft({
                    serviceTypeId: 'natural-stone-applications',
                    serviceId: 'slab-supply-only',
                    stoneIds: ['marble'],
                    stones: [
                        {
                            stoneId: 'marble',
                            quantity: 2,
                            slabSize: '160 x 320 cm',
                            thickness: '0.75 in',
                            edge: 'Straight Edge',
                            finish: 'Honed',
                        },
                    ],
                }),
            ),
        );

        assert.equal(
            readInquiryDraft(USER).draft.stones[0].thickness,
            '0.75 in',
        );

        // The same record as an older page wrote it: no thickness key at all.
        const record = JSON.parse(memory.get(KEY));
        delete record.draft.stones[0].thickness;
        memory.set(KEY, JSON.stringify(record));

        const [row] = readInquiryDraft(USER).draft.stones;

        assert.equal(row.thickness, '');
        assert.equal(row.slabSize, '160 x 320 cm');
    });

    it('comes back after a refresh with its service', () => {
        writeInquiryDraft(
            USER,
            stateOf(
                draft({
                    serviceTypeId: 'custom-stone-furniture',
                    serviceId: 'dining-tables',
                }),
            ),
        );

        const stored = readInquiryDraft(USER);

        assert.equal(stored.draft.serviceTypeId, 'custom-stone-furniture');
        assert.equal(stored.draft.serviceId, 'dining-tables');
        assert.equal(stored.step, 2);
    });

    it('keeps a draft of nothing but a service type', () => {
        writeInquiryDraft(
            USER,
            stateOf(draft({ serviceTypeId: 'custom-stone-furniture' })),
        );

        assert.equal(
            readInquiryDraft(USER).draft.serviceTypeId,
            'custom-stone-furniture',
        );
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

    it('names each of its stones once, and drops those the server no longer offers', () => {
        const row = (stoneId) => ({
            stoneId,
            quantity: 1,
            edge: 'Straight Edge',
            finish: 'Honed',
        });

        writeInquiryDraft(
            USER,
            stateOf(
                draft({
                    stoneIds: ['marble', 'gone'],
                    stones: [row('marble'), row('gone')],
                }),
            ),
        );

        const stored = readInquiryDraft(USER, catalog);

        assert.deepEqual(draftStoneSlugs(stored), ['marble', 'gone']);

        const kept = keepKnownStones(stored, new Set(['marble']));

        assert.deepEqual(kept.draft.stoneIds, ['marble']);
        assert.deepEqual(
            kept.draft.stones.map(({ stoneId }) => stoneId),
            ['marble'],
        );
    });

    it('drops a service no longer in the catalogue', () => {
        writeInquiryDraft(
            USER,
            stateOf(
                draft({
                    serviceTypeId: 'custom-stone-furniture',
                    serviceId: 'retired',
                }),
            ),
        );

        const { draft: stored } = readInquiryDraft(USER, catalog);

        assert.equal(stored.serviceTypeId, 'custom-stone-furniture');
        assert.equal(stored.serviceId, '');
    });

    it('drops a service type no longer in the catalogue, with its service', () => {
        writeInquiryDraft(
            USER,
            stateOf(
                draft({ serviceTypeId: 'gone', serviceId: 'dining-tables' }),
            ),
        );

        const { draft: stored } = readInquiryDraft(USER, catalog);

        assert.equal(stored.serviceTypeId, '');
        assert.equal(stored.serviceId, '');
    });
});
