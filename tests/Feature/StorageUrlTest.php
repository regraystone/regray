<?php

use App\Http\Resources\ShowcaseCardResource;
use Illuminate\Support\Facades\Storage;

test('public disk thumbnails use a relative storage URL', function (): void {
    config(['filesystems.public_disk' => 'public']);
    Storage::fake('public');

    $url = ShowcaseCardResource::resolveThumbnailUrl('projects/a.jpg');

    expect($url)->toBe('/storage/projects/a.jpg');
});

test('S3 public disk thumbnails use the configured CDN URL', function (): void {
    config([
        'filesystems.public_disk' => 's3-public',
        'filesystems.disks.s3-public.url' => 'https://cdn.test',
    ]);
    Storage::fake('s3-public', ['url' => config('filesystems.disks.s3-public.url')]);

    $url = ShowcaseCardResource::resolveThumbnailUrl('projects/a.jpg');

    expect($url)->toStartWith('https://cdn.test/')->toEndWith('projects/a.jpg');
});

test('root-relative thumbnail paths are returned unchanged', function (string $disk): void {
    config(['filesystems.public_disk' => $disk]);
    Storage::fake($disk);

    $url = ShowcaseCardResource::resolveThumbnailUrl('/images/x.jpg');

    expect($url)->toBe('/images/x.jpg');
})->with(['public', 's3-public']);

test('private S3 files are isolated from the public S3 disk', function (): void {
    Storage::fake('s3-private');
    Storage::fake('s3-public');

    Storage::disk('s3-private')->put('inquiries/private.txt', 'Private inquiry');

    Storage::disk('s3-private')->assertExists('inquiries/private.txt');
    Storage::disk('s3-public')->assertMissing('inquiries/private.txt');
});

test('legacy /storage/ prefixed paths resolve like relative paths on the public disk', function (): void {
    config(['filesystems.public_disk' => 'public']);
    Storage::fake('public');

    $url = ShowcaseCardResource::resolveThumbnailUrl('/storage/projects/a.jpg');

    expect($url)->toBe('/storage/projects/a.jpg');
});

test('legacy /storage/ prefixed paths resolve to CDN URLs on s3-public disk', function (): void {
    config([
        'filesystems.public_disk' => 's3-public',
        'filesystems.disks.s3-public.url' => 'https://cdn.test',
    ]);
    Storage::fake('s3-public', ['url' => config('filesystems.disks.s3-public.url')]);

    $url = ShowcaseCardResource::resolveThumbnailUrl('/storage/projects/a.jpg');

    expect($url)->toStartWith('https://cdn.test/')->toEndWith('projects/a.jpg');
});

test('no seeder data files contain /storage/ strings', function (): void {
    $seedDataDir = base_path('database/seeders/data');
    $files = glob($seedDataDir.'/*.php');

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)
            ->not->toContain('/storage/', 'File '.basename($file)." still contains '/storage/' references");
    }
});
