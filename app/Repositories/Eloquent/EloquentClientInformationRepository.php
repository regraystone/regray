<?php

namespace App\Repositories\Eloquent;

use App\Models\ClientInformation;
use App\Models\ClientInformationSnapshot;
use App\Models\Inquiry;
use App\Models\User;
use App\Repositories\Contracts\ClientInformationRepositoryInterface;

class EloquentClientInformationRepository implements ClientInformationRepositoryInterface
{
    public function forUser(User $user): ?ClientInformation
    {
        return ClientInformation::query()->find($user->id);
    }

    public function saveForUser(User $user, array $values): ClientInformation
    {
        /** @var ClientInformation $information */
        $information = ClientInformation::query()->firstOrNew(['user_id' => $user->id]);

        // The owner is the primary key, deliberately kept out of `$fillable`
        // so no request can set it. Assign it here, from the signed-in user.
        $information->user_id = $user->id;
        $information->fill($values);
        $information->save();

        return $information;
    }

    public function snapshotForInquiry(Inquiry $inquiry, array $values): ClientInformationSnapshot
    {
        /** @var ClientInformationSnapshot $snapshot */
        $snapshot = ClientInformationSnapshot::query()->firstOrNew(['inquiry_id' => $inquiry->id]);

        // The inquiry is the primary key and is likewise not mass assignable.
        $snapshot->inquiry_id = $inquiry->id;
        $snapshot->fill($values);
        $snapshot->save();

        return $snapshot;
    }
}
