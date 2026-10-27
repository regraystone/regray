<?php

namespace App\Actions\Inquiry;

use App\Data\WrittenFiles;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @phpstan-import-type ProjectInput from InquiryService
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
     * @param  array{projects: list<ProjectInput>}  $input
     * @return list<Inquiry>
     */
    public function execute(User $client, array $input): array
    {
        $written = new WrittenFiles;

        try {
            return DB::transaction(
                fn (): array => $this->inquiryService->create($client, $input, $written),
            );
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
