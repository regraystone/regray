/* oxlint-disable typescript/no-floating-promises -- node:test registers each test; there is nothing to await. */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    FILE_GONE,
    NOT_PREVIEWABLE,
    OPEN_FAILED,
    POPUP_BLOCKED,
    downloadUrl,
    formatLabel,
    openPreview,
    previewErrorMessage,
} from '../../resources/js/lib/inquiry-file-preview.ts';

const URL = '/dashboard/inquiry/INQ-1/files/abc.pdf';

/** A stand-in for the tab window.open returns, recording what was done to it. */
const fakeTab = () => ({
    opener: 'the page',
    location: { href: 'about:blank' },
    closed: false,
    close() {
        this.closed = true;
    },
});

describe('previewErrorMessage', () => {
    it('names a missing file, an unpreviewable type, and anything else', () => {
        assert.equal(previewErrorMessage(404), FILE_GONE);
        assert.equal(previewErrorMessage(415), NOT_PREVIEWABLE);
        assert.equal(previewErrorMessage(500), OPEN_FAILED);
        assert.equal(previewErrorMessage(429), OPEN_FAILED);
        assert.equal(previewErrorMessage(null), OPEN_FAILED);
    });
});

describe('downloadUrl', () => {
    it('asks for the download, whether or not the URL has a query already', () => {
        assert.equal(downloadUrl(URL), `${URL}?download=1`);
        assert.equal(downloadUrl(`${URL}?v=2`), `${URL}?v=2&download=1`);
    });
});

describe('formatLabel', () => {
    it('reads the server label in capitals, and a missing one as FILE', () => {
        assert.equal(formatLabel('PDF'), 'PDF');
        assert.equal(formatLabel('docx'), 'DOCX');
        assert.equal(formatLabel(' '), 'FILE');
    });
});

describe('openPreview', () => {
    it('opens the tab before checking, then sends it to the file, cut off from the page', async () => {
        const tab = fakeTab();
        const calls = [];
        const pending = openPreview(
            URL,
            () => {
                calls.push('open');

                return tab;
            },
            async (url) => {
                calls.push(`check ${url}`);

                return { ok: true, status: 200 };
            },
        );

        // The tab is open before anything is awaited, inside the click.
        assert.deepEqual(calls, ['open', `check ${URL}`]);
        assert.equal(await pending, null);
        assert.equal(tab.location.href, URL);
        assert.equal(tab.opener, null);
        assert.equal(tab.closed, false);
    });

    it('asks for pop-ups when the browser blocks the tab, and checks nothing', async () => {
        let checked = false;

        const message = await openPreview(
            URL,
            () => null,
            async () => {
                checked = true;

                return { ok: true, status: 200 };
            },
        );

        assert.equal(message, POPUP_BLOCKED);
        assert.equal(checked, false);
    });

    it('closes the tab and says why when the file does not answer', async () => {
        for (const [status, expected] of [
            [404, FILE_GONE],
            [415, NOT_PREVIEWABLE],
            [500, OPEN_FAILED],
        ]) {
            const tab = fakeTab();

            const message = await openPreview(
                URL,
                () => tab,
                async () => ({ ok: false, status }),
            );

            assert.equal(message, expected);
            assert.equal(tab.closed, true);
            assert.equal(tab.location.href, 'about:blank');
        }
    });

    it('closes the tab on a network failure', async () => {
        const tab = fakeTab();

        const message = await openPreview(
            URL,
            () => tab,
            async () => {
                throw new TypeError('Failed to fetch');
            },
        );

        assert.equal(message, OPEN_FAILED);
        assert.equal(tab.closed, true);
    });
});
