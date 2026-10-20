<?php

use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Events\InquiryStatusChanged;
use App\Models\Message;

beforeEach(function (): void {
    seedInquiryCatalog();
});

it('posts one inquiry card into the inquiry\'s group when its status changes', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-C1']);
    $group = groupConversation([messagingStaff(Role::Sales), $client], attributes: ['inquiry_id' => $inquiry->id]);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C1'))->assertRedirect();

    $card = Message::query()->where('conversation_id', $group->id)->sole();

    expect($card->type)->toBe(MessageType::InquiryCard)
        ->and($card->inquiry_id)->toBe($inquiry->id)
        ->and($card->announced_status)->toBe(InquiryStatus::Cancelled->value);
});

it('does not post the same status change twice when the listener runs again', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-C2']);
    $group = groupConversation([$client], attributes: ['inquiry_id' => $inquiry->id]);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C2'))->assertRedirect();
    event(new InquiryStatusChanged($inquiry->refresh(), InquiryStatus::Pending, InquiryStatus::Cancelled, $client->id));

    expect(Message::query()->where('conversation_id', $group->id)->count())->toBe(1);
});

it('posts nothing when the inquiry has no group', function (): void {
    $client = inquiryClient();
    makeInquiry($client, attributes: ['reference' => 'INQ-C3']);
    supportThreadFor($client);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C3'))->assertRedirect();

    expect(Message::query()->count())->toBe(0);
});
