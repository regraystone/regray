/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    ATTACHMENT_ERRORS,
    addAttachments,
    formatFileSize,
} from '../../resources/js/lib/message-attachments.ts';

const MB = 1024 * 1024;
const file = (name, size = 1024) => ({ name, size });

describe('addAttachments', () => {
    it('adds photos, PDFs and Word documents, whatever the case of the extension', () => {
        const result = addAttachments(
            [file('a.pdf')],
            [file('Site.JPG'), file('b.webp'), file('Quote.DOCX')],
        );

        assert.deepEqual(
            result.files.map((item) => item.name),
            ['a.pdf', 'Site.JPG', 'b.webp', 'Quote.DOCX'],
        );
        assert.equal(result.error, null);
    });

    it('leaves out a type it does not take, and keeps the rest', () => {
        const result = addAttachments(
            [],
            [file('notes.html'), file('plan.pdf'), file('noext')],
        );

        assert.deepEqual(
            result.files.map((item) => item.name),
            ['plan.pdf'],
        );
        assert.equal(result.error, ATTACHMENT_ERRORS.type);
    });

    it('leaves out a file over 10 MB', () => {
        const result = addAttachments([], [file('big.pdf', 10 * MB + 1)]);

        assert.deepEqual(result.files, []);
        assert.equal(result.error, ATTACHMENT_ERRORS.fileSize);
    });

    it('stops at 5 files', () => {
        const current = [1, 2, 3, 4].map((n) => file(`${n}.png`));
        const result = addAttachments(current, [file('5.png'), file('6.png')]);

        assert.equal(result.files.length, 5);
        assert.equal(result.error, ATTACHMENT_ERRORS.count);
    });

    it('stops before the files together pass 19 MB', () => {
        const result = addAttachments(
            [file('a.pdf', 9 * MB)],
            [file('b.pdf', 9 * MB), file('c.pdf', 2 * MB), file('d.pdf', MB)],
        );

        assert.deepEqual(
            result.files.map((item) => item.name),
            ['a.pdf', 'b.pdf', 'd.pdf'],
        );
        assert.equal(result.error, ATTACHMENT_ERRORS.totalSize);
    });

    it('reports the first reason when several files are refused', () => {
        const result = addAttachments(
            [],
            [file('a.exe'), file('b.pdf', 11 * MB)],
        );

        assert.equal(result.error, ATTACHMENT_ERRORS.type);
    });
});

describe('formatFileSize', () => {
    it('reads as bytes, kilobytes or megabytes', () => {
        assert.equal(formatFileSize(850), '850 B');
        assert.equal(formatFileSize(120 * 1024), '120 KB');
        assert.equal(formatFileSize(2.44 * MB), '2.4 MB');
    });
});
