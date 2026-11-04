import { crc32 } from 'node:zlib';
import type { Page } from '@playwright/test';
import {
    api,
    expect,
    fixtures,
    openConversation,
    test,
} from './support/fixtures';

/*
 * J. Attachments. Marketing A ("Marketing"), marketing B and sales share
 * "E2E Chat"; the client is not in it.
 */

type Upload = { name: string; mimeType: string; buffer: Buffer };

/** A real one-pixel PNG, so the server's content check agrees it is one. */
const png = (name: string): Upload => ({
    name,
    mimeType: 'image/png',
    buffer: Buffer.from(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'base64',
    ),
});

const pdf = (name: string): Upload => ({
    name,
    mimeType: 'application/pdf',
    buffer: Buffer.from(`%PDF-1.4\n% ${name}\n%%EOF\n`),
});

const unique = (label: string): string =>
    `${label} ${Date.now()}-${Math.floor(Math.random() * 1000)}`;

/** A real JPEG or WebP, drawn and encoded by the browser itself. */
const canvasImage = async (
    page: Page,
    name: string,
    mimeType: 'image/jpeg' | 'image/webp',
): Promise<Upload> => {
    const base64 = await page.evaluate((type) => {
        const canvas = document.createElement('canvas');
        canvas.width = 16;
        canvas.height = 16;
        const context = canvas.getContext('2d');

        if (context !== null) {
            context.fillStyle = '#7a5c3e';
            context.fillRect(0, 0, 16, 16);
        }

        return canvas.toDataURL(type).split(',')[1];
    }, mimeType);

    return { name, mimeType, buffer: Buffer.from(base64, 'base64') };
};

/**
 * A real (minimal) Word document: a zip whose first entry is
 * [Content_Types].xml and which holds word/document.xml, which is what the
 * server's content check looks for. Stored, not compressed, so it needs no
 * zip library.
 */
const docx = (name: string): Upload => {
    const entries: Array<[string, string]> = [
        [
            '[Content_Types].xml',
            '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        ],
        [
            '_rels/.rels',
            '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        ],
        [
            'word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Quote</w:t></w:r></w:p></w:body></w:document>',
        ],
    ];
    const locals: Buffer[] = [];
    const centrals: Buffer[] = [];
    let offset = 0;

    for (const [path, text] of entries) {
        const fileName = Buffer.from(path);
        const data = Buffer.from(text);
        const crc = crc32(data);

        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4);
        local.writeUInt16LE(0x21, 12);
        local.writeUInt32LE(crc, 14);
        local.writeUInt32LE(data.length, 18);
        local.writeUInt32LE(data.length, 22);
        local.writeUInt16LE(fileName.length, 26);
        locals.push(local, fileName, data);

        const central = Buffer.alloc(46);
        central.writeUInt32LE(0x02014b50, 0);
        central.writeUInt16LE(20, 4);
        central.writeUInt16LE(20, 6);
        central.writeUInt16LE(0x21, 14);
        central.writeUInt32LE(crc, 16);
        central.writeUInt32LE(data.length, 20);
        central.writeUInt32LE(data.length, 24);
        central.writeUInt16LE(fileName.length, 28);
        central.writeUInt32LE(offset, 42);
        centrals.push(central, fileName);

        offset += local.length + fileName.length + data.length;
    }

    const directory = Buffer.concat(centrals);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(entries.length, 8);
    end.writeUInt16LE(entries.length, 10);
    end.writeUInt32LE(directory.length, 12);
    end.writeUInt32LE(offset, 16);

    return {
        name,
        mimeType:
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        buffer: Buffer.concat([...locals, directory, end]),
    };
};

test('43 · a photo and a PDF reach the other member live, open for them, and stay closed to someone outside', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    const outsider = await sessions.open('client');
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);

    const text = unique('Site visit');
    const photoName = `${unique('kitchen')}.png`;
    const planName = `${unique('plan')}.pdf`;

    await a.page
        .getByTestId('composer-file-input')
        .setInputFiles([png(photoName), pdf(planName)]);
    await expect(a.page.getByTestId('composer-file')).toHaveCount(2);
    await a.page.getByTestId('composer-input').fill(text);

    const sent = a.page.waitForResponse(
        (response) =>
            /\/dashboard\/inbox\/\d+\/messages$/.test(response.url()) &&
            response.request().method() === 'POST',
    );
    await a.page.getByRole('button', { name: 'Send message' }).click();
    expect((await sent).status()).toBe(201);
    await expect(a.page.getByTestId('composer-file')).toHaveCount(0);

    const bubble = b.page
        .getByTestId('message-bubble')
        .filter({ hasText: text });
    await expect(bubble).toBeVisible({ timeout: 5_000 });

    const photo = bubble.getByRole('img', { name: photoName });
    await expect(photo).toBeVisible();
    await expect
        .poll(() =>
            photo.evaluate((image) => (image as HTMLImageElement).naturalWidth),
        )
        .toBeGreaterThan(0);

    const file = bubble.getByTestId('attachment-file');
    await expect(file).toContainText(planName);
    const href = (await file.getAttribute('href')) ?? '';
    expect(href).toMatch(/^\/dashboard\/inbox\/attachments\/\d+\?download=1$/);

    expect((await api(b.page, 'GET', href)).status).toBe(200);
    sessions.allowConsole(/status of 403 .*\/dashboard\/inbox\/attachments\//);
    await outsider.page.goto('/dashboard/inbox');
    expect((await api(outsider.page, 'GET', href)).status).toBe(403);
});

test('44 · the composer refuses a file type it does not take, and sends photos alone', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    await openConversation(a.page, chat.conversationId);

    // An empty box is one line with no scrollbar.
    expect(
        await a.page
            .getByTestId('composer-input')
            .evaluate(
                (box) =>
                    box.scrollHeight <= box.clientHeight &&
                    getComputedStyle(box).overflowY === 'hidden',
            ),
    ).toBe(true);

    await a.page.getByTestId('composer-file-input').setInputFiles([
        {
            name: 'notes.html',
            mimeType: 'text/html',
            buffer: Buffer.from('<p>hi</p>'),
        },
    ]);
    await expect(a.page.getByTestId('composer-file-error')).toHaveText(
        'Send photos (JPG, PNG, WebP), PDF or Word (DOCX) files.',
    );
    await expect(a.page.getByTestId('composer-file')).toHaveCount(0);
    await expect(
        a.page.getByRole('button', { name: 'Send message' }),
    ).toBeDisabled();

    const photoName = `${unique('alone')}.png`;
    await a.page
        .getByTestId('composer-file-input')
        .setInputFiles([png(photoName)]);
    await expect(a.page.getByTestId('composer-file-error')).toHaveCount(0);
    await a.page.getByRole('button', { name: 'Send message' }).click();

    await expect(
        a.page
            .getByTestId('message-bubble')
            .getByRole('img', { name: photoName }),
    ).toBeVisible();
    await expect(
        a.page.getByTestId('conversation-row').filter({ hasText: chat.name }),
    ).toContainText('You: Sent a photo');
});

test('45 · every type it takes (JPG, JPEG, PNG, WebP, PDF, DOCX) arrives, photos open in the lightbox, documents download', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    const b = await sessions.open('marketingB');
    await openConversation(a.page, chat.conversationId);
    await openConversation(b.page, chat.conversationId);

    const stamp = unique('every').replace(/\s/g, '-');
    const photos = [
        await canvasImage(a.page, `${stamp}-site.jpg`, 'image/jpeg'),
        png(`${stamp}-swatch.png`),
        await canvasImage(a.page, `${stamp}-edge.webp`, 'image/webp'),
    ];
    const documents = [pdf(`${stamp}-plan.pdf`), docx(`${stamp}-quote.docx`)];
    const jpeg = await canvasImage(a.page, `${stamp}-slab.jpeg`, 'image/jpeg');

    /** Attach, send, and wait until the server has taken it. */
    const sendFiles = async (uploads: Upload[], text: string) => {
        await a.page.getByTestId('composer-file-input').setInputFiles(uploads);
        await expect(a.page.getByTestId('composer-file')).toHaveCount(
            uploads.length,
        );
        await a.page.getByTestId('composer-input').fill(text);
        const sent = a.page.waitForResponse(
            (response) =>
                /\/dashboard\/inbox\/\d+\/messages$/.test(response.url()) &&
                response.request().method() === 'POST',
        );
        await a.page.getByRole('button', { name: 'Send message' }).click();
        expect((await sent).status()).toBe(201);
        await expect(a.page.getByTestId('composer-file')).toHaveCount(0);
    };

    // Five types in one message (the most a message takes), then .jpeg alone.
    const firstText = `${stamp} all types`;
    await sendFiles([...photos, ...documents], firstText);
    await sendFiles([jpeg], `${stamp} jpeg`);

    const bubble = (text: string) =>
        b.page.getByTestId('message-bubble').filter({ hasText: text });
    await expect(bubble(firstText)).toBeVisible({ timeout: 5_000 });

    // Photos: a thumbnail each, actually loaded through the download route.
    for (const photo of [...photos, jpeg]) {
        const thumbnail = b.page
            .getByTestId('attachment-photo')
            .getByRole('img', { name: photo.name });
        await expect(thumbnail).toBeVisible();
        await expect
            .poll(() =>
                thumbnail.evaluate(
                    (image) => (image as HTMLImageElement).naturalWidth,
                ),
            )
            .toBeGreaterThan(0);
    }
    await expect(bubble(firstText).getByTestId('attachment-photo')).toHaveCount(
        3,
    );

    // Clicking a photo opens it full size in the lightbox; a click closes it.
    await b.page
        .getByTestId('attachment-photo')
        .getByRole('img', { name: photos[2].name })
        .click();
    const lightbox = b.page.locator('[data-test="image-lightbox"]');
    await expect(lightbox).toBeVisible();
    const enlarged = lightbox.getByRole('img', { name: photos[2].name });
    await expect(enlarged).toBeVisible();
    await expect
        .poll(() =>
            enlarged.evaluate(
                (image) => (image as HTMLImageElement).naturalWidth,
            ),
        )
        .toBeGreaterThan(0);
    await lightbox.click();
    await expect(lightbox).toBeHidden();

    // Documents: a row each that downloads under the sender's name and type.
    const rows = bubble(firstText).getByTestId('attachment-file');
    await expect(rows).toHaveCount(2);

    for (const [index, document] of documents.entries()) {
        const row = rows.nth(index);
        await expect(row).toContainText(document.name);
        const href = (await row.getAttribute('href')) ?? '';
        const response = await b.page.request.get(href);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain(document.mimeType);
        expect(response.headers()['content-disposition']).toBe(
            `attachment; filename=${document.name}`,
        );
        expect(Buffer.from(await response.body()).equals(document.buffer)).toBe(
            true,
        );
    }
});

test('46 · files dragged anywhere over the conversation show the drop overlay and land in the composer, ready to send', async ({
    sessions,
}) => {
    const { chat } = fixtures();
    const a = await sessions.open('marketingA');
    await openConversation(a.page, chat.conversationId);

    const stamp = unique('dropped').replace(/\s/g, '-');
    const uploads = [png(`${stamp}-photo.png`), pdf(`${stamp}-plan.pdf`)];

    // A real DataTransfer holding real files, as the OS hands to the page.
    const dataTransfer = await a.page.evaluateHandle(
        (files) => {
            const transfer = new DataTransfer();

            for (const file of files) {
                const bytes = Uint8Array.from(atob(file.base64), (character) =>
                    character.charCodeAt(0),
                );
                transfer.items.add(
                    new File([bytes], file.name, { type: file.mimeType }),
                );
            }

            return transfer;
        },
        uploads.map((upload) => ({
            name: upload.name,
            mimeType: upload.mimeType,
            base64: upload.buffer.toString('base64'),
        })),
    );

    // Over the message list, not the composer: the whole thread takes it.
    const list = a.page.getByTestId('thread-scroll');
    const overlay = a.page.getByTestId('drop-overlay');

    await list.dispatchEvent('dragenter', { dataTransfer });
    await expect(overlay).toContainText('Drop files to attach');
    await list.dispatchEvent('dragleave', { dataTransfer });
    await expect(overlay).toBeHidden();

    await list.dispatchEvent('dragenter', { dataTransfer });
    await list.dispatchEvent('dragover', { dataTransfer });
    await list.dispatchEvent('drop', { dataTransfer });
    await expect(overlay).toBeHidden();

    const chips = a.page.getByTestId('composer-file');
    await expect(chips).toHaveCount(2);
    await expect(chips.nth(0)).toContainText(uploads[0].name);
    await expect(chips.nth(1)).toContainText(uploads[1].name);

    const sent = a.page.waitForResponse(
        (response) =>
            /\/dashboard\/inbox\/\d+\/messages$/.test(response.url()) &&
            response.request().method() === 'POST',
    );
    await a.page.getByRole('button', { name: 'Send message' }).click();
    expect((await sent).status()).toBe(201);

    const bubble = a.page
        .getByTestId('message-bubble')
        .filter({ has: a.page.getByRole('img', { name: uploads[0].name }) });
    await expect(bubble).toBeVisible();
    await expect(bubble.getByTestId('attachment-file')).toContainText(
        uploads[1].name,
    );
});
