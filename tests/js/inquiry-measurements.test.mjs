/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    answerProblem,
    formatMeasure,
    measurementProblem,
    normalizedStoneAnswer,
    parseSlabSize,
    parseThickness,
    questionForStone,
    thicknessAfterSizeChange,
    thicknessOptionsFor,
} from '../../resources/js/lib/inquiry-flow.ts';

const field = (slug, label) => ({
    slug,
    label,
    scope: 'stone',
    answerType: 'text',
    required: true,
    allowsCustom: true,
    options: [],
    suggestions: [],
});

const slabSize = field('slab_size', 'Slab size');
const thickness = field('thickness', 'Thickness');
const option = (label) => ({ value: label, label });

/** Cosmic Black as the E2E seed fixes it: 60 x 120 cm comes in 20 mm only. */
const cosmicBlack = {
    variants: [
        { size: '3.15 x 1.98 m', thickness: '20 mm' },
        { size: '3.15 x 1.98 m', thickness: '0.75 in' },
        { size: '160 x 320 cm', thickness: '20 mm' },
        { size: '160 x 320 cm', thickness: '0.75 in' },
        { size: '60 x 120 cm', thickness: '20 mm' },
    ],
    sizes: ['60 x 120 cm', '160 x 320 cm', '3.15 x 1.98 m'].map(option),
    thicknesses: ['20 mm', '0.75 in'].map(option),
    sizeNote: null,
};

/** Crazy Cut: pieces with no set size. */
const crazyCut = {
    variants: ['2 cm', '3 cm', '4 cm'].map((label) => ({
        size: null,
        thickness: label,
    })),
    sizes: [],
    thicknesses: ['2 cm', '3 cm', '4 cm'].map(option),
    sizeNote: 'Diameter 15–40 cm',
};

const values = (options) => options.map(({ value }) => value);

describe('measurements', () => {
    it('writes a number without trailing zeros, as the server does', () => {
        assert.equal(formatMeasure('160.00'), '160');
        assert.equal(formatMeasure('1.60'), '1.6');
        assert.equal(formatMeasure('0.75'), '0.75');
        assert.equal(formatMeasure(20), '20');
    });

    it('reads a slab size in every unit, whatever its case and spacing', () => {
        assert.deepEqual(parseSlabSize('160 x 320 cm'), {
            width: '160',
            length: '320',
            unit: 'cm',
        });
        assert.deepEqual(parseSlabSize(' 1.6X3.2M '), {
            width: '1.6',
            length: '3.2',
            unit: 'm',
        });
        assert.equal(parseSlabSize('63 x 126 in')?.unit, 'in');
        assert.equal(parseSlabSize('5x10 FT')?.unit, 'ft');
    });

    it('reads a thickness in every unit', () => {
        assert.deepEqual(parseThickness('20 mm'), { value: '20', unit: 'mm' });
        assert.deepEqual(parseThickness('2CM'), { value: '2', unit: 'cm' });
        assert.deepEqual(parseThickness('0.75 in'), {
            value: '0.75',
            unit: 'in',
        });
    });

    it('refuses what the server refuses, in its words', () => {
        const format =
            'Enter the thickness as a number and a unit, like 20 mm.';

        for (const written of ['20', '20 ft', 'abc', '20.555 mm']) {
            assert.equal(measurementProblem('thickness', written), format);
        }

        assert.equal(
            measurementProblem('thickness', '0 mm'),
            'Enter a thickness greater than 0.',
        );
        assert.equal(
            measurementProblem('thickness', '10000 mm'),
            'Enter a smaller thickness.',
        );
        assert.equal(measurementProblem('thickness', '1.25 in'), null);

        for (const written of ['160x320', '160 x 320 yd', 'abc']) {
            assert.equal(
                measurementProblem('size', written),
                'Enter the slab size as width x length and a unit, like 160 x 320 cm.',
            );
        }

        assert.equal(
            measurementProblem('size', '0 x 320 cm'),
            'Enter a slab size greater than 0.',
        );
        assert.equal(measurementProblem('size', '1.6 x 3.2 m'), null);
    });

    it('asks a slab size and thickness as required measurements', () => {
        assert.equal(answerProblem(thickness, ''), 'Enter the thickness.');
        assert.equal(answerProblem(slabSize, '  '), 'Enter the slab size.');
        assert.equal(
            answerProblem(thickness, '20'),
            'Enter the thickness as a number and a unit, like 20 mm.',
        );
        assert.equal(answerProblem(thickness, '27 mm'), null);
    });

    it('compares two rows by the measurement, not by how it was typed', () => {
        assert.equal(
            normalizedStoneAnswer(slabSize, '160X320 CM'),
            '160 x 320 cm',
        );
        assert.equal(normalizedStoneAnswer(thickness, '20MM'), '20 mm');
        assert.equal(normalizedStoneAnswer(thickness, 'abc'), 'abc');
    });
});

describe('thicknessOptionsFor', () => {
    it('lists every thickness of the stone while no size is chosen', () => {
        assert.deepEqual(values(thicknessOptionsFor(cosmicBlack, '')), [
            '20 mm',
            '0.75 in',
        ]);
    });

    it("lists only the thicknesses a listed size is cut in, in the stone's order", () => {
        assert.deepEqual(
            values(thicknessOptionsFor(cosmicBlack, '60 x 120 cm')),
            ['20 mm'],
        );
        assert.deepEqual(
            values(thicknessOptionsFor(cosmicBlack, '160 x 320 cm')),
            ['20 mm', '0.75 in'],
        );
        // However the size was written.
        assert.deepEqual(
            values(thicknessOptionsFor(cosmicBlack, '60X120 CM')),
            ['20 mm'],
        );
    });

    it("lists every thickness for a size of the reader's own, and for a stone with no set sizes", () => {
        assert.deepEqual(
            values(thicknessOptionsFor(cosmicBlack, '150 x 300 cm')),
            ['20 mm', '0.75 in'],
        );
        assert.deepEqual(values(thicknessOptionsFor(crazyCut, '50 x 50 cm')), [
            '2 cm',
            '3 cm',
            '4 cm',
        ]);
        assert.deepEqual(values(thicknessOptionsFor(undefined, '')), []);
    });
});

describe('thicknessAfterSizeChange', () => {
    it('clears a listed thickness the new size is not cut in', () => {
        assert.equal(
            thicknessAfterSizeChange(cosmicBlack, '60 x 120 cm', '0.75 in'),
            '',
        );
    });

    it("keeps a listed thickness that still fits, and one of the reader's own", () => {
        assert.equal(
            thicknessAfterSizeChange(cosmicBlack, '60 x 120 cm', '20 mm'),
            '20 mm',
        );
        assert.equal(
            thicknessAfterSizeChange(cosmicBlack, '60 x 120 cm', '25 mm'),
            '25 mm',
        );
        assert.equal(
            thicknessAfterSizeChange(cosmicBlack, '150 x 300 cm', '0.75 in'),
            '0.75 in',
        );
        assert.equal(thicknessAfterSizeChange(cosmicBlack, '', ''), '');
    });
});

describe('questionForStone', () => {
    it("lists the stone's sizes, and the thicknesses its chosen size is cut in", () => {
        assert.deepEqual(
            values(questionForStone(slabSize, cosmicBlack).suggestions),
            ['60 x 120 cm', '160 x 320 cm', '3.15 x 1.98 m'],
        );
        assert.deepEqual(
            values(
                questionForStone(thickness, cosmicBlack, {
                    slabSize: '60 x 120 cm',
                }).suggestions,
            ),
            ['20 mm'],
        );
    });

    it('lists no size for a stone with none, leaving only Other', () => {
        assert.deepEqual(questionForStone(slabSize, crazyCut).suggestions, []);
        assert.deepEqual(
            values(questionForStone(thickness, crazyCut).suggestions),
            ['2 cm', '3 cm', '4 cm'],
        );
    });

    it('leaves every other question as the service sent it', () => {
        const finish = { ...field('finish', 'Finish'), answerType: 'choice' };

        assert.equal(questionForStone(finish, cosmicBlack), finish);
    });
});
