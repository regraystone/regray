/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    archiveServiceDescription,
    fulfillmentLabel,
    matchesSearch,
    newQuestionProblem,
    notOfferedReason,
    questionHint,
    serviceMeta,
    typeArchiveBlocker,
} from '../../resources/js/lib/service-admin.ts';

describe('serviceMeta', () => {
    it('counts questions, lines and inquiries, singular for one', () => {
        assert.equal(
            serviceMeta({
                questions: [{}, {}, {}],
                features: [''],
                inquiriesCount: 1,
            }),
            '3 questions · 1 included · 1 inquiry',
        );
        assert.equal(
            serviceMeta({ questions: [], features: [], inquiriesCount: 0 }),
            '0 questions · 0 included · 0 inquiries',
        );
    });
});

describe('notOfferedReason', () => {
    const service = {
        isActive: true,
        isOffered: false,
        requiresStones: true,
        stoneTypes: [],
    };

    it('says nothing for an offered or a deliberately hidden service', () => {
        assert.equal(
            notOfferedReason({ ...service, isOffered: true }, true),
            null,
        );
        assert.equal(
            notOfferedReason({ ...service, isActive: false }, true),
            null,
        );
    });

    it('names the hidden type first, then the missing stone types', () => {
        assert.match(
            notOfferedReason(service, false),
            /service type is hidden/,
        );
        assert.match(notOfferedReason(service, true), /no stone type/);
    });
});

describe('questionHint', () => {
    it('says where it is asked and what it takes', () => {
        assert.equal(
            questionHint({
                scope: 'project',
                answerType: 'choice',
                options: ['Wood', 'Steel'],
            }),
            'For the project · One of: Wood, Steel',
        );
        assert.equal(
            questionHint({ scope: 'stone', answerType: 'choice', options: [] }),
            'For each stone · One from a list',
        );
        assert.equal(
            questionHint({
                scope: 'project',
                answerType: 'number',
                options: ['2', '4'],
            }),
            'For the project · A number (suggests 2, 4)',
        );
        assert.equal(
            questionHint({
                scope: 'project',
                answerType: 'boolean',
                options: [],
            }),
            'For the project · Yes or no',
        );
    });
});

describe('archiving', () => {
    it('says which inquiries keep an archived service', () => {
        assert.match(
            archiveServiceDescription(2),
            /Its 2 inquiries keep it\.$/,
        );
        assert.equal(
            archiveServiceDescription(0),
            'It leaves this page and the Inquire page.',
        );
    });

    it('blocks archiving a type while it has services, as the server does', () => {
        assert.equal(typeArchiveBlocker(0), null);
        assert.equal(
            typeArchiveBlocker(1),
            'Move or archive its service first.',
        );
        assert.equal(
            typeArchiveBlocker(5),
            'Move or archive its 5 services first.',
        );
    });
});

describe('fulfillmentLabel and matchesSearch', () => {
    it('names both ways the work reaches the client', () => {
        assert.equal(
            fulfillmentLabel('delivery_or_pickup'),
            'Delivered or picked up',
        );
        assert.equal(fulfillmentLabel('installed'), 'Installed on site');
    });

    it('matches the name or description, ignoring case and spaces', () => {
        const service = {
            name: 'Dining Tables',
            description: 'Made-to-order stone tables.',
        };

        assert.ok(matchesSearch(service, '  dining '));
        assert.ok(matchesSearch(service, 'STONE'));
        assert.ok(matchesSearch(service, ''));
        assert.ok(!matchesSearch(service, 'flooring'));
    });
});

describe('newQuestionProblem', () => {
    const limits = { questionNameMax: 100, choicesMax: 10 };
    const taken = ['Project area (sqm)', 'Table base'];
    const ok = { name: 'Sink cut-out', answerType: 'text', choices: [] };

    it('adds a written question with a kind of answer', () => {
        assert.equal(newQuestionProblem(ok, taken, limits), null);
        assert.equal(
            newQuestionProblem(
                { name: 'Sink', answerType: 'choice', choices: ['One', 'Two'] },
                taken,
                limits,
            ),
            null,
        );
    });

    it('says what is missing, as the server would', () => {
        assert.equal(
            newQuestionProblem({ ...ok, name: '  ' }, taken, limits),
            'Write the question.',
        );
        assert.equal(
            newQuestionProblem({ ...ok, answerType: '' }, taken, limits),
            'Choose what kind of answer it takes.',
        );
        assert.equal(
            newQuestionProblem(
                { ...ok, answerType: 'choice', choices: ['One'] },
                taken,
                limits,
            ),
            'List at least two choices.',
        );
    });

    it('refuses a question the list has, whatever its case', () => {
        assert.equal(
            newQuestionProblem({ ...ok, name: ' table BASE ' }, taken, limits),
            'That question already exists. Choose it from the list.',
        );
    });
});
