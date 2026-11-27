/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

const { addDaysIso, latestNeededBy, neededByProblem } =
    await import('../../resources/js/lib/inquiry-flow.ts');

describe('the date a project is needed by', () => {
    const today = '2026-11-26';

    it('counts days by the calendar', () => {
        assert.equal(addDaysIso(today, 5), '2026-12-01');
        assert.equal(addDaysIso('2028-02-27', 2), '2028-02-29');
    });

    it("is at least the service's fewest days ahead", () => {
        assert.equal(
            neededByProblem('2026-11-30', { leadDays: 5 }, today),
            'Choose a date at least 5 days from today.',
        );
        assert.equal(
            neededByProblem('2026-12-01', { leadDays: 5 }, today),
            null,
        );
        assert.equal(
            neededByProblem('2026-11-26', { leadDays: 1 }, today),
            'Choose a date at least 1 day from today.',
        );
    });

    it('may be today for a service with no fewest days', () => {
        assert.equal(neededByProblem(today, { leadDays: 0 }, today), null);
        assert.equal(neededByProblem(today, undefined, today), null);
    });

    it('keeps the date an edit already has, however soon', () => {
        assert.equal(
            neededByProblem('2026-11-27', { leadDays: 5 }, today, '2026-11-27'),
            null,
        );
    });

    it('is within the next two years', () => {
        assert.equal(latestNeededBy(today), '2028-11-26');
        assert.equal(neededByProblem('2028-11-26', undefined, today), null);
        assert.equal(
            neededByProblem('2028-11-27', undefined, today),
            'Choose a date within the next 2 years.',
        );
    });
});
