<?php

use App\Enums\InquiryStatus;
use App\Policies\InquiryActions;

it('gives each status exactly the buttons in the table', function (string $status, array $table): void {
    $actions = new InquiryActions;
    $status = InquiryStatus::from($status);

    foreach (['view', 'update', 'cancel', 'invoice'] as $action) {
        expect($actions->for($status)[$action])->toBe(expectedAction($table[$action]), "{$status->value} {$action}");
        expect($actions->{$action}($status)->toArray())->toBe(expectedAction($table[$action]), "{$status->value} {$action}");
    }
})->with('inquiry button table');

it('only gives a reason to a button that is shown and locked', function (): void {
    foreach (InquiryStatus::cases() as $status) {
        foreach ((new InquiryActions)->for($status) as $action) {
            expect(array_key_exists('reason', $action))->toBe($action['visible'] && ! $action['enabled']);
        }
    }
});

it('lists the actions in the order the card renders them', function (): void {
    expect(array_keys((new InquiryActions)->for(InquiryStatus::Pending)))->toBe(['view', 'update', 'cancel', 'invoice']);
});
