<?php

namespace App\Actions\Inquiry;

use App\Data\WrittenFiles;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\User;
use App\Services\InquiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @phpstan-import-type ProjectInput from InquiryService
 *
 * @phpstan-type InquiryInput array{projects: list<ProjectInput>}
 */
class CreateInquiryAction
{
    public function __construct(
        public readonly InquiryService $inquiryService,
    ) {}

    /**
     * Write the inquiries, one per project, in one transaction.
     *
     * A failure rolls the rows back and deletes every file the service had
     * already written before it is rethrown, so no half of a request survives:
     * neither a row without its files nor a file without its row.
     *
     * Staff opening one for a lead's client pass the lead and themselves.
     * Each inquiry carries its own copy of the address it was sent with, so
     * nothing else is written beside it.
     *
     * @param  InquiryInput  $input
     * @return list<Inquiry>
     */
    public function execute(User $client, array $input, ?Lead $lead = null, ?User $by = null): array
    {
        return $this->writing(fn (WrittenFiles $written): array => $this->inquiryService->create($client, $input, $written, $lead, $by));
    }

    /**
     * Staff open the inquiries for a direct (walk-in) client, in one
     * transaction, the same way, each owned by `$owner`.
     *
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry>
     */
    public function executeForDirectClient(Client $record, array $input, User $by, User $owner): array
    {
        return $this->writing(fn (WrittenFiles $written): array => $this->inquiryService->createForDirectClient($record, $input, $written, $by, $owner));
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
