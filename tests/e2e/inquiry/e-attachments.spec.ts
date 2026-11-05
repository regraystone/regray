import type { Page } from '@playwright/test';
import { inquiryFixtures } from '../support/accounts';
import { expect, inquiryReferences, php, test } from '../support/fixtures';
import {
    CATALOG,
    chooseLocation,
    continueButton,
    deadlineDate,
    deadlineTime,
    expectStep,
    futureDate,
    openInquire,
    openMoreDetails,
    pickService,
    sendAndReadReference,
} from '../support/inquiry-flow';

/* E. Attachments on the Requirements step. */

type Upload = { name: string; mimeType: string; buffer: Buffer };

/** A real one-pixel PNG, so the server's content check agrees it is one. */
const PNG = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    'base64',
);

const pdf = (name: string, padding = 0): Upload => ({
    name,
    mimeType: 'application/pdf',
    buffer: Buffer.concat([
        Buffer.from(`%PDF-1.4\n% ${name}\n`),
        Buffer.alloc(padding, 0x20),
        Buffer.from('\n%%EOF\n'),
    ]),
});

const png = (name: string): Upload => ({
    name,
    mimeType: 'image/png',
    buffer: PNG,
});

const fileInput = (page: Page) => page.locator('input[type="file"]');

/** Each attachment row, by the file name it shows. */
const attachmentRow = (page: Page, name: string) =>
    page.locator('li').filter({ has: page.getByTitle(name, { exact: true }) });

/** A visit up to the Requirements step, where the attachments are. */
const openVisitRequirements = async (page: Page): Promise<void> => {
    await openInquire(page);
    await pickService(page, CATALOG.visitService);
    await continueButton(page).click();
    await expectStep(page, 4);
    await openMoreDetails(page);
};

const finishVisit = async (page: Page): Promise<void> => {
    await chooseLocation(page, 'Makati');
    await deadlineDate(page).fill(futureDate(8));
    await deadlineTime(page).fill('15:00');
    await page.getByRole('button', { name: 'Continue to review' }).click();
    await expectStep(page, 5);
};

test('21 · a PDF and a PNG appear with their names; removing one takes it off', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openVisitRequirements(page);

    await fileInput(page).setInputFiles([
        pdf('site-plan.pdf'),
        png('photo.png'),
    ]);

    for (const name of ['site-plan.pdf', 'photo.png']) {
        await expect(attachmentRow(page, name)).toBeVisible();
        await expect(attachmentRow(page, name)).toContainText('Uploaded');
    }

    await page.getByRole('button', { name: 'Remove site-plan.pdf' }).click();

    await expect(attachmentRow(page, 'site-plan.pdf')).toHaveCount(0);
    await expect(attachmentRow(page, 'photo.png')).toBeVisible();
    await expect(page.getByText('site-plan.pdf removed')).toBeVisible();
});

test('22 · a .gif and a file over 10 MB are refused with a message, and nothing reaches the server', async ({
    actors,
}) => {
    const { page } = await actors.open('client');
    await openVisitRequirements(page);

    const sent: string[] = [];
    page.on('request', (request) => sent.push(request.url()));

    await fileInput(page).setInputFiles([
        { name: 'drawing.gif', mimeType: 'image/gif', buffer: PNG },
        pdf('too-big.pdf', 10 * 1024 * 1024),
    ]);

    await expect(
        page.getByText(
            "drawing.gif isn't a supported type. Use PDF, PNG, JPG or JPEG.",
        ),
    ).toBeVisible();
    await expect(page.getByText('too-big.pdf is over 10 MB.')).toBeVisible();
    await expect(attachmentRow(page, 'drawing.gif')).toHaveCount(0);
    await expect(attachmentRow(page, 'too-big.pdf')).toHaveCount(0);

    expect(sent, 'requests made while the files were refused').toEqual([]);
});

test('23 · attachments are kept on the private disk and a guessed /storage URL never serves them', async ({
    actors,
}) => {
    const { clientAId, disk } = inquiryFixtures();
    const { page } = await actors.open('client');
    actors.allowConsole(/status of 40[34]/);
    await openVisitRequirements(page);

    await fileInput(page).setInputFiles([pdf('private-plan.pdf')]);
    await expect(attachmentRow(page, 'private-plan.pdf')).toContainText(
        'Uploaded',
    );
    await finishVisit(page);
    const reference = await sendAndReadReference(page);
    expect(inquiryReferences(clientAId)[0]).toBe(reference);

    const stored = php<{
        disk: string;
        path: string;
        exists: boolean;
        visibility: string | null;
    }>(
        `(function () { $file = App\\Models\\InquiryFile::query()->whereHas('inquiry', fn ($q) => $q->where('reference', '${reference}'))->firstOrFail(); return ['disk' => $file->disk, 'path' => $file->path, 'exists' => Illuminate\\Support\\Facades\\Storage::disk($file->disk)->exists($file->path), 'visibility' => config('filesystems.disks.'.$file->disk.'.visibility')]; })()`,
    );

    expect(stored.disk).toBe(disk);
    expect(stored.disk).not.toBe('public');
    expect(stored.visibility).not.toBe('public');
    expect(stored.exists).toBe(true);
    expect(stored.path).toMatch(new RegExp(`^inquiries/${reference}/[^/]+$`));

    const fileName = stored.path.split('/').pop() ?? '';
    for (const guess of [
        `/storage/${stored.path}`,
        `/storage/inquiries/${reference}/${fileName}`,
        `/storage/inquiries/${reference}/private-plan.pdf`,
        `/${stored.path}`,
    ]) {
        const response = await page.request.get(guess, { maxRedirects: 0 });

        expect([403, 404], `${guess} answered ${response.status()}`).toContain(
            response.status(),
        );
    }
});

test('24 · ten attachments are accepted and the eleventh is refused', async ({
    actors,
}) => {
    const { clientAId } = inquiryFixtures();
    const { page } = await actors.open('client');
    await openVisitRequirements(page);

    const files = Array.from({ length: 11 }, (_, index) =>
        pdf(`sheet-${String(index + 1).padStart(2, '0')}.pdf`),
    );
    await fileInput(page).setInputFiles(files);

    await expect(
        page.getByText('You can attach up to 10 files.'),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: /^Remove sheet-/ }),
    ).toHaveCount(10);
    await expect(attachmentRow(page, 'sheet-11.pdf')).toHaveCount(0);
    await expect(page.getByText('Uploaded', { exact: true })).toHaveCount(10);

    await finishVisit(page);
    const reference = await sendAndReadReference(page);
    expect(inquiryReferences(clientAId)[0]).toBe(reference);
    expect(
        php<number>(
            `App\\Models\\InquiryFile::query()->whereHas('inquiry', fn ($q) => $q->where('reference', '${reference}'))->count()`,
        ),
    ).toBe(10);
});
