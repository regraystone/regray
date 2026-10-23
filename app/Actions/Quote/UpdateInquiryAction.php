<?php

namespace App\Actions\Quote;

use App\Data\WrittenFiles;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @phpstan-import-type ProjectUpdateInput from InquiryService
 */
class UpdateInquiryAction
{
    public function __construct(
        public readonly InquiryService $inquiryService,
    ) {}

    /**
     * Write the edit in one transaction, which also holds the inquiry's row lock.
     *
     * A failure rolls the rows back and deletes every new file the service had
     * already written, so nothing of a half-made edit survives. Files the edit
     * removed are deleted from the disk only after the transaction commits, so a
     * failed edit never loses a file its rows still point at.
     *
     * @param  array{projects: list<ProjectUpdateInput>}  $input
     */
    public function execute(User $client, string $reference, array $input, ?string $ipAddress, ?string $userAgent): Inquiry
    {
        $written = new WrittenFiles;

        try {
            $result = DB::transaction(
                fn (): array => $this->inquiryService->update($client, $reference, $input, $written, $ipAddress, $userAgent),
            );
        } catch (Throwable $exception) {
            if ($written->paths() !== []) {
                Storage::disk(config('inquiry.disk'))->delete($written->paths());
            }

            throw $exception;
        }

        foreach ($result['removedFiles'] as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        return $result['inquiry'];
    }
}
