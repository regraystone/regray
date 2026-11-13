<?php

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/*
 * An inquiry's files, served from the private disk to whoever may open the
 * inquiry and see the file on its details page, by the file's stored name.
 */

beforeEach(function (): void {
    Storage::fake('local');
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-F1']);
});

/** A file on the inquiry with these bytes on the disk, as an upload leaves it. */
function storedInquiryFile(Inquiry $inquiry, DocumentType $type, string $contents = '%PDF-1.4 plan', string $mimeType = 'application/pdf', string $originalName = 'Kitchen plan.pdf', ?int $revision = null): InquiryFile
{
    $file = addInquiryFile($inquiry, $type, revision: $revision);
    $file->update([
        'original_name' => $originalName,
        'mime_type' => $mimeType,
        'size_bytes' => strlen($contents),
        'sha256' => hash('sha256', $contents),
    ]);
    Storage::disk('local')->put($file->path, $contents);

    return $file;
}

/** The file's route as `$viewer`, previewed, or downloaded with `$download`. */
function openInquiryFile(mixed $test, User $viewer, InquiryFile $file, bool $download = false, string $method = 'GET'): TestResponse
{
    $url = route('inquiry.files.show', [$file->inquiry->reference, $file->stored_name, ...$download ? ['download' => 1] : []]);

    return $test->actingAs($viewer)->call($method, $url);
}

it('shows the client their own attachment in the browser, byte for byte, never cached or sniffed', function (): void {
    $file = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);

    $response = openInquiryFile($this, $this->client, $file)->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and(hash('sha256', $response->streamedContent()))->toBe($file->sha256);
});

it('saves the file under the uploader\'s name with ?download=1', function (): void {
    $file = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);

    $disposition = openInquiryFile($this, $this->client, $file, download: true)->assertOk()->headers->get('Content-Disposition');

    expect($disposition)->toStartWith('attachment;')
        ->toContain('Kitchen plan.pdf')
        ->not->toContain($file->stored_name);
});

it('answers HEAD with the headers and no body', function (): void {
    $file = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);

    $response = openInquiryFile($this, $this->client, $file, method: 'HEAD')->assertOk();

    expect($response->getContent())->toBe('')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf');
});

it('finds a file only by its stored name, never by its id', function (): void {
    $file = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);

    $this->actingAs($this->client)->get(route('inquiry.files.show', ['INQ-F1', $file->id]))->assertNotFound();
    $this->actingAs($this->client)->get(route('inquiry.files.show', ['INQ-F1', 'no-such-file.pdf']))->assertNotFound();
});

it('gives a client a 404 for another client\'s file, the job order and an older quotation', function (): void {
    $theirs = storedInquiryFile(makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-F2']), DocumentType::ClientAttachment);
    $jobOrder = storedInquiryFile($this->inquiry, DocumentType::JobOrder);
    $older = storedInquiryFile($this->inquiry, DocumentType::Quotation, revision: 1);
    $latest = storedInquiryFile($this->inquiry, DocumentType::Quotation, revision: 2);

    openInquiryFile($this, $this->client, $theirs)->assertNotFound();
    openInquiryFile($this, $this->client, $jobOrder)->assertNotFound();
    openInquiryFile($this, $this->client, $older)->assertNotFound();
    openInquiryFile($this, $this->client, $latest)->assertOk();
});

it('serves staff what their details page lists, and nothing to staff who do not read the inquiry', function (): void {
    $attachment = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);
    $jobOrder = storedInquiryFile($this->inquiry, DocumentType::JobOrder);
    $older = storedInquiryFile($this->inquiry, DocumentType::Quotation, revision: 1);
    storedInquiryFile($this->inquiry, DocumentType::Quotation, revision: 2);
    $sales = User::factory()->role(Role::Sales)->create();
    $technical = User::factory()->role(Role::Technical)->create();
    letStaffRead($this->inquiry, $sales);
    assignInquiry($this->inquiry, $technical);

    // Sales sends the quotations, so sees how the quote moved.
    openInquiryFile($this, $sales, $older)->assertOk();
    openInquiryFile($this, $technical, $older)->assertNotFound();
    openInquiryFile($this, $technical, $attachment)->assertOk();
    openInquiryFile($this, $technical, $jobOrder)->assertOk();
    openInquiryFile($this, User::factory()->role(Role::Technical)->create(), $attachment)->assertNotFound();
});

it('refuses to preview a type the browser is not trusted with, and still downloads it', function (): void {
    $file = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment, '<svg onload="alert(1)"/>', 'image/svg+xml', 'drawing.svg');

    openInquiryFile($this, $this->client, $file)->assertStatus(415);

    $response = openInquiryFile($this, $this->client, $file, download: true)->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->headers->get('Content-Type'))->toBe('image/svg+xml')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('gives a 404 for a removed file, and for one missing from the disk, which it logs', function (): void {
    $removed = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);
    $removed->delete();
    $missing = storedInquiryFile($this->inquiry, DocumentType::ClientAttachment);
    Storage::disk('local')->delete($missing->path);
    Log::spy();

    openInquiryFile($this, $this->client, $removed)->assertNotFound();
    openInquiryFile($this, $this->client, $missing)->assertNotFound();

    Log::shouldHaveReceived('warning')->once();
});

it('sends each file on the details page with the URL it opens by', function (): void {
    storedInquiryFile($this->inquiry, DocumentType::ClientAttachment, "\x89PNG site", 'image/png', 'Site.png');
    storedInquiryFile($this->inquiry, DocumentType::Quotation, revision: 1);
    $page = $this->actingAs($this->client)->get(route('quote.show', 'INQ-F1'))->viewData('page')['props']['inquiry'];
    $attachment = $page['project']['requirements']['attachments'][0];
    $quotation = $page['documents'][0]['files'][0];

    expect($attachment)->toMatchArray(['name' => 'Site.png', 'previewable' => true, 'format' => 'PNG'])
        ->and($quotation)->toMatchArray(['name' => 'Kitchen plan.pdf', 'previewable' => true, 'format' => 'PDF'])
        ->and($attachment['uploadedAt'])->toBe(InquiryFile::query()->where('original_name', 'Site.png')->sole()->created_at->toIso8601String());

    $this->actingAs($this->client)->get($attachment['url'])->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->actingAs($this->client)->get($quotation['url'].'?download=1')->assertOk();
});
