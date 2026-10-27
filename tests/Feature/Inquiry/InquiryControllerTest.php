<?php

use App\Actions\Inquiry\CreateInquiryAction;
use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Inquiry;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
});

/*
 * The POST routes carry the same guards as their GET twins plus
 * `inquiries.create`. A client holds `quotations.view` and so reaches the quote
 * URL; the inquiry URL is the staff list's, so it takes `inquiries.view` and
 * the client is turned away from it exactly as they are from its GET.
 */
it('lets a client post to the quote URL', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'success')
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.title', 'Kitchen counters')
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.reference', Inquiry::query()->value('reference'));

    expect(Inquiry::query()->value('client_id'))->toBe($client->id);
});

it('names every inquiry it opened when more than one project was sent', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(), inquiryProjectInput(['name' => 'Bath'])]])
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.title', 'Kitchen counters')
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.reference', Inquiry::query()->orderBy('id')->pluck('reference')->implode(', '));

    expect(Inquiry::query()->count())->toBe(2);
});

it('lets a staff role that can read and create inquiries post to the inquiry URL', function (): void {
    $this->actingAs(inquiryClient(Role::Marketing))
        ->post(route('inquiry.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect()
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'success');

    expect(Inquiry::query()->count())->toBe(1);
});

it('keeps a client out of the inquiry URL, as it does on the GET', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)->get(route('inquiry.inquire'))->assertForbidden();
    $this->actingAs($client)
        ->post(route('inquiry.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertForbidden();
});

it('refuses a staff role without inquiries.create on both URLs', function (): void {
    $sales = inquiryClient(Role::Sales);

    $this->actingAs($sales)->post(route('inquiry.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertForbidden();
    $this->actingAs($sales)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertForbidden();

    expect(Inquiry::query()->count())->toBe(0);
});

it('redirects a guest to log in', function (): void {
    $this->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertRedirect(route('login'));
});

it('flashes an error and keeps the page when the write fails', function (): void {
    $this->mock(CreateInquiryAction::class)
        ->shouldReceive('execute')
        ->andThrow(new RuntimeException('database down'));

    $this->actingAs(inquiryClient())
        ->from(route('quote.inquire'))
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect(route('quote.inquire'))
        ->assertSessionHas(SessionKey::FLASH_DATA.'.inquiry.status', 'error');
});

it('sends the Inquire page its options', function (): void {
    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page
            ->component('inquire')
            ->has('services', 7, fn ($service) => $service->hasAll(['id', 'name', 'features', 'image', 'standalone', 'group']))
            ->where('services.5.standalone', true)
            ->has('stones.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail', 'recommended', 'category', 'description', 'specifications']))
            ->has('applications.0', fn ($application) => $application->hasAll(['id', 'name', 'image']))
            ->has('edgeProfiles', 8)
            ->has('finishes.0', fn ($finish) => $finish->hasAll(['id', 'name', 'isAdvisory']))
            ->where('locations', config('inquiry.locations')));
});

it('leaves an inactive edge profile off the Inquire page', function (): void {
    EdgeProfile::query()->where('name', 'Half Bevel')->update(['is_active' => false]);

    $this->actingAs(inquiryClient())
        ->get(route('quote.inquire'))
        ->assertInertia(fn ($page) => $page->has('edgeProfiles', 7));
});

it('hands the page its flash after the redirect back', function (): void {
    $this->actingAs(inquiryClient())
        ->from(route('quote.inquire'))
        ->followingRedirects()
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertInertia(fn ($page) => $page
            ->component('inquire')
            ->hasFlash('inquiry.status', 'success')
            ->hasFlash('inquiry.title', 'Kitchen counters')
            ->hasFlash('inquiry.reference', Inquiry::query()->value('reference')));
});
