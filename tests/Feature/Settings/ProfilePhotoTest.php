<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A client's profile photo: set and removed from the Profile page, kept on
 * the public disk, and shown wherever they appear (the header's shared
 * `auth.avatar`, and to staff on an inquiry's details). Staff have no
 * profile to keep one yet.
 */
beforeEach(function (): void {
    seedInquiryCatalog();
    Storage::fake(config('filesystems.public_disk'));
});

function publicDisk(): Filesystem
{
    return Storage::disk(config('filesystems.public_disk'));
}

test('a client adds a photo, kept on the public disk and shared as their avatar', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $path = $client->fresh()->profile->avatar_path;

    expect($path)->toStartWith('avatars/')->not->toContain('me.jpg');
    publicDisk()->assertExists($path);

    $this->get(route('profile.show'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.avatar', fn (?string $url): bool => $url !== null && str_contains($url, $path)));
});

test('a new photo replaces the old one, whose file is deleted', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('first.png')]);
    $first = $client->fresh()->profile->avatar_path;

    $this->actingAs($client)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('second.webp')]);
    $second = $client->fresh()->profile->avatar_path;

    expect($second)->not->toBe($first);
    publicDisk()->assertMissing($first);
    publicDisk()->assertExists($second);
});

test('removing the photo deletes its file and brings back the initials', function (): void {
    $client = inquiryClient();
    $this->actingAs($client)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg')]);
    $path = $client->fresh()->profile->avatar_path;

    $this->actingAs($client)->delete(route('profile.photo.destroy'))->assertRedirect();

    expect($client->fresh()->profile->avatar_path)->toBeNull();
    publicDisk()->assertMissing($path);
    $this->get(route('profile.show'))->assertInertia(fn (Assert $page) => $page->where('auth.avatar', null));
});

test('only a JPG, PNG or WebP photo within the size limit is taken', function (UploadedFile $file, string $message): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('profile.photo.update'), ['photo' => $file])
        ->assertSessionHasErrors(['photo' => $message]);

    expect($client->fresh()->profile?->avatar_path)->toBeNull();
})->with([
    'a PDF' => fn () => [UploadedFile::fake()->create('me.pdf', 100, 'application/pdf'), 'Choose a JPG, PNG or WebP photo.'],
    'too large' => fn () => [UploadedFile::fake()->image('me.jpg')->size(config('profile.photo_max_kb') + 1), 'The photo can be '.config('profile.photo_max_kb').' KB at most.'],
]);

test('staff cannot set or remove a photo: they have no profile to keep one', function (): void {
    $staff = User::factory()->role(Role::Sales)->create();

    $this->actingAs($staff)
        ->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg')])
        ->assertForbidden();
    $this->actingAs($staff)->delete(route('profile.photo.destroy'))->assertForbidden();

    expect($staff->fresh()->profile)->toBeNull();
});

test('staff see the client\'s photo, name and company on the inquiry\'s details; the client is not sent their own', function (): void {
    $client = inquiryClient();
    $this->actingAs($client)->post(route('profile.photo.update'), ['photo' => UploadedFile::fake()->image('me.jpg')]);
    $client->profile->update(['company' => 'Ayala Land']);
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-PH1']);
    $sales = User::factory()->role(Role::Sales)->create();
    letStaffRead($inquiry, $sales);

    $this->actingAs($sales)->get(route('inquiry.show', 'INQ-PH1'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('inquiry.clientAccount.name', $client->name)
            ->where('inquiry.clientAccount.company', 'Ayala Land')
            ->where('inquiry.clientAccount.avatar', fn (?string $url): bool => $url !== null && str_contains($url, (string) $client->fresh()->profile->avatar_path)));

    $this->actingAs($client)->get(route('quote.show', 'INQ-PH1'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('inquiry.clientAccount', null));
});
