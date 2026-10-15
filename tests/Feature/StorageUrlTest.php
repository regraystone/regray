<?php

use App\Http\Resources\ProjectCardResource;
use Illuminate\Support\Facades\Storage;

test('public disk thumbnails use a relative storage URL', function (): void {
    config(['filesystems.public_disk' => 'public']);
    Storage::fake('public');

    $url = ProjectCardResource::resolveThumbnailUrl('projects/a.jpg');

    expect($url)->toBe('/storage/projects/a.jpg');
});

test('S3 public disk thumbnails use the configured CDN URL', function (): void {
    config([
        'filesystems.public_disk' => 's3-public',
        'filesystems.disks.s3-public.url' => 'https://cdn.test',
    ]);
    Storage::fake('s3-public', ['url' => config('filesystems.disks.s3-public.url')]);

    $url = ProjectCardResource::resolveThumbnailUrl('projects/a.jpg');

    expect($url)->toStartWith('https://cdn.test/')->toEndWith('projects/a.jpg');
});

test('root-relative thumbnail paths are returned unchanged', function (string $disk): void {
    config(['filesystems.public_disk' => $disk]);
    Storage::fake($disk);

    $url = ProjectCardResource::resolveThumbnailUrl('/images/x.jpg');

    expect($url)->toBe('/images/x.jpg');
})->with(['public', 's3-public']);

test('private S3 files are isolated from the public S3 disk', function (): void {
    Storage::fake('s3-private');
    Storage::fake('s3-public');

    Storage::disk('s3-private')->put('inquiries/private.txt', 'Private inquiry');

    Storage::disk('s3-private')->assertExists('inquiries/private.txt');
    Storage::disk('s3-public')->assertMissing('inquiries/private.txt');
});
