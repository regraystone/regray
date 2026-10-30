<?php

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Policies\InquiryActions;

/** An inquiry shown at `$status`, in memory only. */
function inquiryShowing(InquiryStatus $status): Inquiry
{
    $statuses = roleStatusesShowing($status);

    return (new Inquiry)->forceFill([
        'sales_status' => $statuses['sales_status'],
        'payment_status' => $statuses['payment_status'],
        'process_status' => $statuses['process_status'],
        'has_job_order' => $statuses['job_order'],
    ]);
}

it('gives each status exactly the buttons in the table', function (string $status, array $table): void {
    $actions = new InquiryActions;
    $inquiry = inquiryShowing(InquiryStatus::from($status));

    foreach (['view', 'update', 'cancel', 'invoice'] as $action) {
        expect($actions->for($inquiry)[$action])->toBe(expectedAction($table[$action]), "{$status} {$action}");
        expect($actions->{$action}($inquiry)->toArray())->toBe(expectedAction($table[$action]), "{$status} {$action}");
    }
})->with('inquiry button table');

it('locks Update on a logistics hold, since the client has approved by then', function (): void {
    $inquiry = inquiryShowing(InquiryStatus::Production)->forceFill(['process_status' => 'on_hold']);

    expect($inquiry->status)->toBe(InquiryStatus::OnHold)
        ->and((new InquiryActions)->updateRefusal($inquiry))->toBe(InquiryActions::UPDATE_LOCKED_WHEN_QUOTED);
});

it('only gives a reason to a button that is shown and locked', function (): void {
    foreach (InquiryStatus::cases() as $status) {
        foreach ((new InquiryActions)->for(inquiryShowing($status)) as $action) {
            expect(array_key_exists('reason', $action))->toBe($action['visible'] && ! $action['enabled']);
        }
    }
});

it('lists the actions in the order the card renders them', function (): void {
    expect(array_keys((new InquiryActions)->for(inquiryShowing(InquiryStatus::Pending))))->toBe(['view', 'update', 'cancel', 'invoice']);
});
