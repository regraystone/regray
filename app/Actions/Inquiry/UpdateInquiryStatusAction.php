<?php

namespace App\Actions\Inquiry;

use App\Data\WrittenFiles;
use App\Enums\InquiryStatusTrack;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryStatusUpdateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateInquiryStatusAction
{
    public function __construct(
        public readonly InquiryStatusUpdateService $statusUpdates,
    ) {}

    /**
     * Move the status in one transaction, which also holds the inquiry's row
     * lock. A failure rolls everything back and deletes the files already
     * written, so no half of an update survives.
     *
     * @param  array<string, list<UploadedFile>>  $files
     */
    public function execute(User $by, string $reference, InquiryStatusTrack $track, string $to, string $note, array $files, ?string $amount): Inquiry
    {
        return $this->writing(fn (WrittenFiles $written): Inquiry => $this->statusUpdates->update($by, $reference, $track, $to, $note, $files, $amount, $written));
    }

    /**
     * Upload the job order after the quote, the same way.
     *
     * @param  list<UploadedFile>  $files
     */
    public function jobOrder(User $by, string $reference, string $note, array $files): Inquiry
    {
        return $this->writing(fn (WrittenFiles $written): Inquiry => $this->statusUpdates->attachJobOrder($by, $reference, $note, $files, $written));
    }

    /** @param  \Closure(WrittenFiles): Inquiry  $write */
    private function writing(\Closure $write): Inquiry
    {
        $written = new WrittenFiles;

        try {
            return DB::transaction(fn (): Inquiry => $write($written));
        } catch (Throwable $exception) {
            if ($written->paths() !== []) {
                Storage::disk(config('inquiry.disk'))->delete($written->paths());
            }

            throw $exception;
        }
    }
}
