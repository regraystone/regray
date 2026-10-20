<?php

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Stone;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->slugs = Stone::query()->orderBy('id')->limit(2)->pluck('slug')->all();
});

/** An inquiry with two projects, a status history and a stored file. */
function detailedInquiry(mixed $test): Inquiry
{
    $inquiry = makeInquiry($test->client, [
        ['name' => 'Kitchen', 'stones' => $test->slugs, 'services' => ['natural-stone-supply', 'custom-stone-fabrication']],
        ['name' => 'Bath', 'stones' => [$test->slugs[1]], 'services' => ['natural-stone-supply']],
    ], ['reference' => 'INQ-D1']);

    $inquiry->statusHistory()->create(['from_status' => null, 'to_status' => InquiryStatus::Pending, 'changed_by' => $test->client->id, 'changed_at' => now()->subDay()]);

    $project = $inquiry->projects()->orderBy('sort_order')->first();
    $project->update(['landmark' => 'Near the church', 'area_sqm' => 12.5, 'deadline_date' => '2030-01-02', 'deadline_time' => '09:30:00', 'notes' => 'Ground floor']);
    $project->files()->create(['disk' => 'local', 'path' => 'inquiries/INQ-D1/1/secret-path.pdf', 'original_name' => 'plan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $test->client->id]);

    return $inquiry;
}

it('opens the signed-in client\'s own inquiry', function (): void {
    detailedInquiry($this);

    $response = $this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->assertOk();
    $inquiry = $response->viewData('page')['props']['inquiry'];

    expect($response->viewData('page')['component'])->toBe('inquiry-details')
        ->and($inquiry['reference'])->toBe('INQ-D1')
        ->and($inquiry['status'])->toBe('pending')
        ->and($inquiry['statusHistory'])->toHaveCount(1)
        ->and($inquiry['statusHistory'][0])->toHaveKeys(['status', 'at'])->not->toHaveKey('changed_by')
        ->and(array_column($inquiry['projects'], 'name'))->toBe(['Kitchen', 'Bath'])
        ->and($inquiry['services'])->toHaveCount(2);

    $kitchen = $inquiry['projects'][0];

    expect(array_column($kitchen['stones'], 'id'))->toBe($this->slugs)
        ->and($kitchen['stones'][0])->toMatchArray([
            'quantity' => 1,
            'edge' => EdgeProfile::query()->value('name'),
            'finish' => Finish::query()->value('name'),
        ])
        ->and($kitchen['requirements'])->toMatchArray([
            'location' => 'Makati',
            'landmark' => 'Near the church',
            'areaSqm' => 12.5,
            'neededBy' => '2030-01-02',
            'neededByTime' => '09:30',
            'notes' => 'Ground floor',
        ])
        ->and($kitchen['requirements']['attachments'])->toBe([
            ['id' => 'file-1', 'name' => 'plan.pdf', 'sizeBytes' => 2048, 'mimeType' => 'application/pdf'],
        ]);
});

it('carries nothing a client should not see', function (): void {
    detailedInquiry($this);

    $json = json_encode($this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->viewData('page')['props']['inquiry']);

    foreach (['client_id', 'changed_by', 'handler', 'secret-path', 'inquiries\/INQ', 'sha256', 'uploaded_by', '"disk"', '"path"'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
});

it('gives another client a 404', function (): void {
    detailedInquiry($this);

    $this->actingAs(inquiryClient())->get(route('quote.show', 'INQ-D1'))->assertNotFound();
});

it('answers an unknown and a malformed reference with the same 404', function (): void {
    $this->actingAs($this->client)->get(route('quote.show', 'INQ-NOPE'))->assertNotFound();
    $this->actingAs($this->client)->get(route('quote.show', 'bad ref!'))->assertNotFound();
});

it('does not take quote/inquire for a reference', function (): void {
    $this->actingAs($this->client)->get(route('quote.inquire'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('inquire')->etc());
});

it('turns away a role without quotations.view and a guest', function (): void {
    detailedInquiry($this);

    $this->actingAs(inquiryClient(Role::Sales))->get(route('quote.show', 'INQ-D1'))->assertForbidden();
    auth()->logout();
    $this->get(route('quote.show', 'INQ-D1'))->assertRedirect(route('login'));
});

it('is scoped to the user on the inquiry route as well', function (): void {
    $admin = inquiryClient(Role::Admin);
    makeInquiry($admin, attributes: ['reference' => 'INQ-D2']);
    detailedInquiry($this);

    $this->actingAs($admin)->get(route('inquiry.show', 'INQ-D2'))->assertOk();
    $this->actingAs($admin)->get(route('inquiry.show', 'INQ-D1'))->assertNotFound();
});
