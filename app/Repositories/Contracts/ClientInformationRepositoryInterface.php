<?php

namespace App\Repositories\Contracts;

use App\Models\ClientInformation;
use App\Models\ClientInformationSnapshot;
use App\Models\Inquiry;
use App\Models\User;

interface ClientInformationRepositoryInterface
{
    /** The signed-in user's own saved row, or null when they have none. */
    public function forUser(User $user): ?ClientInformation;

    /**
     * Create or update the user's own row. The user id comes from the caller,
     * never from a request.
     *
     * @param  array{delivery_method: string, location: string|null}  $values
     */
    public function saveForUser(User $user, array $values): ClientInformation;

    /**
     * Copy what the client sent with one inquiry. One snapshot per inquiry.
     *
     * @param  array{delivery_method: string, location: string|null}  $values
     */
    public function snapshotForInquiry(Inquiry $inquiry, array $values): ClientInformationSnapshot;
}
