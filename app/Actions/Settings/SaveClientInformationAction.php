<?php

namespace App\Actions\Settings;

use App\Models\ClientInformation;
use App\Models\User;
use App\Services\ClientInformationService;
use Illuminate\Support\Facades\DB;

/**
 * The Profile save, including its audit row, in one transaction: the account
 * never keeps a saved row without the record that it was saved.
 *
 * @phpstan-import-type ClientInformationInput from ClientInformationService
 */
class SaveClientInformationAction
{
    public function __construct(
        public readonly ClientInformationService $clientInformation,
    ) {}

    /**
     * @param  ClientInformationInput  $input
     */
    public function execute(User $user, array $input, ?string $ipAddress, ?string $userAgent): ClientInformation
    {
        return DB::transaction(fn (): ClientInformation => $this->clientInformation->saveForUser($user, $input, $ipAddress, $userAgent));
    }
}
