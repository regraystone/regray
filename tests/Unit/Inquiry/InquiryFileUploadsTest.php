<?php

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Policies\InquiryFileUploads;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->inquiry = makeInquiry($this->client);
    $this->uploads = new InquiryFileUploads;
});

it('lets only whoever moves sales upload a quotation or a job order: sales, and the admin', function (DocumentType $type): void {
    expect($this->uploads->allows(inquiryClient(Role::Sales), $type, $this->inquiry))->toBeTrue()
        ->and($this->uploads->allows(inquiryClient(Role::Admin), $type, $this->inquiry))->toBeTrue();

    foreach ([Role::Client, Role::Owner, Role::Marketing, Role::Accounting, Role::Technical, Role::Logistics] as $role) {
        $user = inquiryClient($role);

        expect($this->uploads->allows($user, $type, $this->inquiry))->toBeFalse("{$role->value} {$type->value}")
            ->and(fn () => $this->uploads->authorize($user, $type, $this->inquiry))->toThrow(AuthorizationException::class);
    }

    expect($this->uploads->allows($this->client, $type, $this->inquiry))->toBeFalse();
})->with([
    'quotation' => [DocumentType::Quotation],
    'job order' => [DocumentType::JobOrder],
]);

it('lets only whoever moves payment upload its documents: accounting, and the admin', function (DocumentType $type, string $message): void {
    expect($this->uploads->allows(inquiryClient(Role::Accounting), $type, $this->inquiry))->toBeTrue()
        ->and($this->uploads->allows(inquiryClient(Role::Admin), $type, $this->inquiry))->toBeTrue();

    foreach ([Role::Client, Role::Owner, Role::Marketing, Role::Sales, Role::Technical, Role::Logistics] as $role) {
        $user = inquiryClient($role);

        expect($this->uploads->allows($user, $type, $this->inquiry))->toBeFalse($role->value)
            ->and(fn () => $this->uploads->authorize($user, $type, $this->inquiry))->toThrow(AuthorizationException::class, $message);
    }
})->with([
    'billing statement' => [DocumentType::BillingStatement, 'Only accounting can upload a billing statement.'],
    'receipt' => [DocumentType::Receipt, 'Only accounting can upload a receipt.'],
    'invoice' => [DocumentType::Invoice, 'Only accounting can upload an invoice.'],
]);

it('lets only the inquiry\'s own client attach files to it', function (): void {
    expect($this->uploads->allows($this->client, DocumentType::ClientAttachment, $this->inquiry))->toBeTrue()
        ->and($this->uploads->allows(inquiryClient(), DocumentType::ClientAttachment, $this->inquiry))->toBeFalse()
        ->and($this->uploads->allows(inquiryClient(Role::Sales), DocumentType::ClientAttachment, $this->inquiry))->toBeFalse();
});
