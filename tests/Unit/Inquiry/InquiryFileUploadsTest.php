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

it('lets only sales upload a quotation or a job order', function (DocumentType $type): void {
    expect($this->uploads->allows(inquiryClient(Role::Sales), $type, $this->inquiry))->toBeTrue();

    foreach ([Role::Client, Role::Admin, Role::Owner, Role::Marketing, Role::Accounting, Role::Technical, Role::Logistics] as $role) {
        $user = inquiryClient($role);

        expect($this->uploads->allows($user, $type, $this->inquiry))->toBeFalse("{$role->value} {$type->value}")
            ->and(fn () => $this->uploads->authorize($user, $type, $this->inquiry))->toThrow(AuthorizationException::class);
    }

    expect($this->uploads->allows($this->client, $type, $this->inquiry))->toBeFalse();
})->with([
    'quotation' => [DocumentType::Quotation],
    'job order' => [DocumentType::JobOrder],
]);

it('lets only accounting upload a receipt', function (): void {
    expect($this->uploads->allows(inquiryClient(Role::Accounting), DocumentType::Receipt, $this->inquiry))->toBeTrue();

    foreach ([Role::Client, Role::Admin, Role::Owner, Role::Marketing, Role::Sales, Role::Technical, Role::Logistics] as $role) {
        $user = inquiryClient($role);

        expect($this->uploads->allows($user, DocumentType::Receipt, $this->inquiry))->toBeFalse($role->value)
            ->and(fn () => $this->uploads->authorize($user, DocumentType::Receipt, $this->inquiry))->toThrow(AuthorizationException::class, 'Only accounting can upload a receipt.');
    }
});

it('lets only the inquiry\'s own client attach files to it', function (): void {
    expect($this->uploads->allows($this->client, DocumentType::ClientAttachment, $this->inquiry))->toBeTrue()
        ->and($this->uploads->allows(inquiryClient(), DocumentType::ClientAttachment, $this->inquiry))->toBeFalse()
        ->and($this->uploads->allows(inquiryClient(Role::Sales), DocumentType::ClientAttachment, $this->inquiry))->toBeFalse();
});
