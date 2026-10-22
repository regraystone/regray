<?php

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
 * Files sent in a thread: stored on the private disk (s3-private in
 * production), and only served to someone who may read the conversation.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * @param  list<UploadedFile>  $files
 * @param  array<string, mixed>  $extra
 */
function sendFiles(User $sender, Conversation $conversation, array $files, ?string $body = null, array $extra = []): TestResponse
{
    return test()->actingAs($sender)->post(route('message.send', $conversation), [
        'body' => $body,
        'clientKey' => (string) Str::uuid(),
        'attachments' => $files,
        ...$extra,
    ], ['Accept' => 'application/json']);
}

function sitePhoto(): UploadedFile
{
    return UploadedFile::fake()->image('Site photo.jpg', 40, 40);
}

function floorPlan(int $kilobytes = 20): UploadedFile
{
    return UploadedFile::fake()->create('Floor plan.pdf', $kilobytes, 'application/pdf');
}

/** A real (minimal) Word document, so the server's content check sees a DOCX. */
function wordDocument(string $name = 'Quote.docx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Quote</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    return new UploadedFile($path, $name, null, null, true);
}

it('keeps attachments on the private S3 bucket in production and in Laravel storage elsewhere', function (string $environment, ?string $override, string $disk): void {
    // phpunit.xml's APP_ENV is set where env() reads it, so it is swapped there.
    $setVariable = function (string $key, ?string $value): void {
        foreach ([&$_SERVER, &$_ENV] as &$store) {
            if ($value === null) {
                unset($store[$key]);
            } else {
                $store[$key] = $value;
            }
        }

        putenv($value === null ? $key : "{$key}={$value}");
    };
    $original = ['APP_ENV' => Env::get('APP_ENV'), 'MESSAGE_ATTACHMENT_DISK' => Env::get('MESSAGE_ATTACHMENT_DISK')];
    $setVariable('APP_ENV', $environment);
    $setVariable('MESSAGE_ATTACHMENT_DISK', $override);

    try {
        expect((require config_path('messaging.php'))['attachments']['disk'])->toBe($disk);
    } finally {
        foreach ($original as $key => $value) {
            $setVariable($key, $value === null ? null : (string) $value);
        }
    }
})->with([
    'production' => ['production', null, 's3-private'],
    'local development' => ['local', null, 'local'],
    'an explicit override' => ['production', 'local', 'local'],
]);

it('stores a photo and a PDF on the private disk and returns them with the message', function (): void {
    $disk = Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $group = groupConversation([messagingStaff(Role::Sales), $client]);

    $response = sendFiles($client, $group, [sitePhoto(), floorPlan()], 'Here is the kitchen.')->assertCreated();

    $attachments = MessageAttachment::query()->orderBy('id')->get();
    expect($attachments->pluck('original_name')->all())->toBe(['Site photo.jpg', 'Floor plan.pdf'])
        ->and($attachments->pluck('mime_type')->all())->toBe(['image/jpeg', 'application/pdf']);

    foreach ($attachments as $attachment) {
        expect($attachment->path)->toStartWith("messages/{$group->id}/")
            ->and($attachment->path)->not->toContain('Floor plan')
            ->and($attachment->path)->not->toContain('Site photo');
        $disk->assertExists($attachment->path);
    }

    $response->assertJsonPath('message.body', 'Here is the kitchen.')
        ->assertJsonPath('message.attachments.0', [
            'id' => $attachments[0]->id,
            'name' => 'Site photo.jpg',
            'mimeType' => 'image/jpeg',
            'size' => $attachments[0]->size_bytes,
            'isImage' => true,
            'url' => route('message.attachments.show', $attachments[0], absolute: false),
        ])
        ->assertJsonPath('message.attachments.1.isImage', false);
});

it('takes a Word document and serves it as a download', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $sales = messagingStaff(Role::Sales);
    $group = groupConversation([$sales, $client]);

    $response = sendFiles($client, $group, [wordDocument()])->assertCreated()
        ->assertJsonPath('message.attachments.0.name', 'Quote.docx')
        ->assertJsonPath('message.attachments.0.isImage', false);

    $this->actingAs($sales)->get($response->json('message.attachments.0.url').'?download=1')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->assertHeader('Content-Disposition', 'attachment; filename=Quote.docx');
});

it('sends files without any words, and the list says what was sent', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $client->forceFill(['name' => 'Rafael Reyes'])->save();
    $sales = messagingStaff(Role::Sales, 'Sam Cruz');
    $group = groupConversation([$sales, $client]);

    sendFiles($client, $group, [sitePhoto(), sitePhoto()])->assertCreated()->assertJsonPath('message.body', '');

    $this->actingAs($sales)->getJson(route('message.thread', $group))
        ->assertJsonPath('conversation.preview', 'Rafael: Sent 2 photos');

    sendFiles($sales, $group, [floorPlan()])->assertCreated();
    $this->actingAs($sales)->getJson(route('message.thread', $group))
        ->assertJsonPath('conversation.preview', 'You: Sent a file');
});

it('quotes a files-only message as "Attachment" in a reply', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $sales = messagingStaff(Role::Sales);
    $group = groupConversation([$sales, $client]);
    $photoId = sendFiles($client, $group, [sitePhoto()])->json('message.id');

    $this->actingAs($sales)->postJson(route('message.send', $group), [
        'body' => 'Nice',
        'clientKey' => (string) Str::uuid(),
        'replyToId' => $photoId,
    ])->assertCreated()->assertJsonPath('message.replyTo.snippet', 'Attachment');
});

it('refuses what it cannot send, with the reason', function (array $payload, string $field, string $error): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $group = groupConversation([messagingStaff(Role::Sales), $client]);

    sendFiles($client, $group, $payload['attachments'] ?? [], $payload['body'] ?? null)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $error]);

    expect(Message::query()->count())->toBe(0)
        ->and(Storage::disk(config('messaging.attachments.disk'))->allFiles())->toBe([]);
})->with([
    'nothing at all' => [[], 'body', 'Write a message first.'],
    'a type it does not take' => [
        fn () => ['attachments' => [UploadedFile::fake()->create('notes.html', 2, 'text/html')]],
        'attachments.0', 'Send photos (JPG, PNG, WebP), PDF or Word (DOCX) files.',
    ],
    'more than 5 files' => [
        fn () => ['attachments' => array_map(fn () => floorPlan(1), range(1, 6))],
        'attachments', 'Send up to 5 files at a time.',
    ],
    'a file over 10 MB' => [
        fn () => ['attachments' => [floorPlan(10 * 1024 + 1)]],
        'attachments.0', 'Each file can be up to 10 MB.',
    ],
    'more than 19 MB together' => [
        fn () => ['attachments' => [floorPlan(10 * 1024), floorPlan(10 * 1024)]],
        'attachments', 'Send up to 19 MB of files at a time.',
    ],
]);

it('stores the files once when the same send is retried', function (): void {
    $disk = Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $group = groupConversation([messagingStaff(Role::Sales), $client]);
    $key = (string) Str::uuid();

    sendFiles($client, $group, [floorPlan()], extra: ['clientKey' => $key])->assertCreated();
    sendFiles($client, $group, [floorPlan()], extra: ['clientKey' => $key])->assertOk();

    expect(MessageAttachment::query()->count())->toBe(1)
        ->and($disk->allFiles())->toHaveCount(1);
});

it('serves a file to a member, under its own name and type', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $sales = messagingStaff(Role::Sales);
    $group = groupConversation([$sales, $client]);
    $url = sendFiles($client, $group, [floorPlan()])->json('message.attachments.0.url');

    $this->actingAs($sales)->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Floor plan.pdf"')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($sales)->get($url.'?download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="Floor plan.pdf"');
});

it('does not serve a file to anyone who cannot read the conversation', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $group = groupConversation([messagingStaff(Role::Sales), $client]);
    $url = sendFiles($client, $group, [floorPlan()])->json('message.attachments.0.url');

    $this->actingAs(inquiryClient())->get($url)->assertForbidden();
    $this->actingAs(messagingStaff(Role::Marketing, 'Mara'))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));
});

it('stops serving a Regray Team file to the pool once someone else claims the thread', function (): void {
    Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');
    $url = sendFiles($client, $thread, [sitePhoto()])->json('message.attachments.0.url');

    $this->actingAs($mike)->get($url)->assertOk();
    $this->actingAs($mara)->postJson(route('message.claim', $thread))->assertOk();

    $this->actingAs($mike)->get($url)->assertForbidden();
    $this->actingAs($mara)->get($url)->assertOk();
    $this->actingAs($client)->get($url)->assertOk();
});

it('keeps a removed message\'s files on the disk but no longer serves or lists them', function (): void {
    $disk = Storage::fake(config('messaging.attachments.disk'));
    $client = inquiryClient();
    $sales = messagingStaff(Role::Sales);
    $group = groupConversation([$sales, $client]);
    $sent = sendFiles($client, $group, [floorPlan()], 'Plan')->json('message');

    $this->actingAs($client)->deleteJson(route('message.messages.destroy', $sent['id']))
        ->assertOk()
        ->assertJsonPath('message.attachments', []);

    $this->actingAs($sales)->get($sent['attachments'][0]['url'])->assertNotFound();
    $disk->assertExists(MessageAttachment::query()->value('path'));
});

it('sends S3 files through a link that expires after 5 minutes', function (): void {
    config(['messaging.attachments.disk' => 's3-private']);
    Storage::fake('s3-private');
    Storage::disk('s3-private')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration, array $options): string => 'https://private-bucket.test/'.$path
            .'?expires='.$expiration->getTimestamp()
            .'&type='.urlencode($options['ResponseContentType'])
            .'&disposition='.urlencode($options['ResponseContentDisposition']),
    );
    $this->freezeTime();
    $client = inquiryClient();
    $sales = messagingStaff(Role::Sales);
    $group = groupConversation([$sales, $client]);

    $url = sendFiles($client, $group, [floorPlan()])->json('message.attachments.0.url');
    $attachment = MessageAttachment::query()->firstOrFail();

    expect($attachment->disk)->toBe('s3-private');
    $this->actingAs($sales)->get($url)->assertRedirect(
        "https://private-bucket.test/{$attachment->path}?expires=".now()->addMinutes(5)->getTimestamp()
        .'&type='.urlencode('application/pdf')
        .'&disposition='.urlencode('inline; filename="Floor plan.pdf"'),
    );
    $this->actingAs(inquiryClient())->get($url)->assertForbidden();
});
