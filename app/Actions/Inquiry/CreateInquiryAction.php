<?php

namespace App\Actions\Inquiry;

use App\Data\WrittenFiles;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use App\Services\ClientInformationService;
use App\Services\InquiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @phpstan-import-type ProjectInput from InquiryService
 * @phpstan-import-type ClientInformationInput from ClientInformationService
 *
 * @phpstan-type InquiryInput array{projects: list<ProjectInput>, clientInformation?: ClientInformationInput}
 */
class CreateInquiryAction
{
    public function __construct(
        public readonly InquiryService $inquiryService,
        public readonly ClientInformationService $clientInformationService,
    ) {}

    /**
     * Write the inquiries, one per project, in one transaction.
     *
     * A failure rolls the rows back and deletes every file the service had
     * already written before it is rethrown, so no half of a request survives:
     * neither a row without its files nor a file without its row.
     *
     * Staff opening one for a lead's client pass the lead and themselves; the
     * client's own details are absent there, so no snapshot is written.
     *
     * A client sending their own inquiry also carries their "Client
     * Information" delivery answer. Saving it to the Profile, when asked for,
     * and copying it onto every inquiry happen inside this same transaction: a
     * failure leaves neither a half-saved Profile row nor one inquiry with a
     * snapshot and one without.
     *
     * @param  InquiryInput  $input
     * @return list<Inquiry>
     */
    public function execute(User $client, array $input, ?Lead $lead = null, ?User $by = null, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return $this->writing(function (WrittenFiles $written) use ($client, $input, $lead, $by, $ipAddress, $userAgent): array {
            $inquiries = $this->inquiryService->create($client, $input, $written, $lead, $by);

            $this->writeClientInformation($client, $input, $inquiries, $ipAddress, $userAgent);

            return $inquiries;
        });
    }

    /**
     * Keep and copy the client's delivery answer only when the client sent it.
     *
     * @param  InquiryInput  $input
     * @param  list<Inquiry>  $inquiries
     */
    private function writeClientInformation(User $client, array $input, array $inquiries, ?string $ipAddress, ?string $userAgent): void
    {
        $values = $input['clientInformation'] ?? null;

        if ($values === null) {
            return;
        }

        if (($values['saveToProfile'] ?? false) === true) {
            $this->clientInformationService->saveForUser($client, $values, $ipAddress, $userAgent);
        }

        foreach ($inquiries as $inquiry) {
            $this->clientInformationService->snapshotForInquiry($inquiry, $values);
        }
    }

    /**
     * Staff open the inquiries for a direct (walk-in) client, in one
     * transaction, the same way.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry>
     */
    public function executeForDirectClient(Client $record, array $input, User $by): array
    {
        return $this->writing(fn (WrittenFiles $written): array => $this->inquiryService->createForDirectClient($record, $input, $written, $by));
    }

    /**
     * @param  \Closure(WrittenFiles): list<Inquiry>  $write
     * @return list<Inquiry>
     */
    private function writing(\Closure $write): array
    {
        $written = new WrittenFiles;

        try {
            return DB::transaction(fn (): array => $write($written));
        } catch (Throwable $exception) {
            $this->deleteWrittenFiles($written);

            throw $exception;
        }
    }

    private function deleteWrittenFiles(WrittenFiles $written): void
    {
        if ($written->paths() === []) {
            return;
        }

        Storage::disk(config('inquiry.disk'))->delete($written->paths());
    }
}
