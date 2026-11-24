/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { distinctLabels } from '../../resources/js/lib/distinct-labels.ts';

describe('distinctLabels', () => {
    const label = (option) => option.label;

    it('leaves labels that are already distinct as they are', () => {
        assert.deepEqual(
            distinctLabels([{ label: 'Honed' }, { label: 'Polished' }], label),
            ['Honed', 'Polished'],
        );
    });

    it('numbers labels that repeat, ignoring case, in their order', () => {
        assert.deepEqual(
            distinctLabels(
                [{ label: 'Honed' }, { label: 'Polished' }, { label: 'honed' }],
                label,
            ),
            ['Honed (1)', 'Polished', 'honed (2)'],
        );
    });

    it('gives each option a label of its own, so choosing by label finds it', () => {
        const options = [
            { id: 1, label: 'Marble' },
            { id: 2, label: 'Marble' },
        ];
        const labels = distinctLabels(options, label);

        assert.equal(new Set(labels).size, options.length);
    });
});
