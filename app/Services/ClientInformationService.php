<?php

namespace App\Services;

use App\Data\ClientInformationValues;
use App\Enums\AuditEvent;
use App\Enums\DeliveryMethod;
use App\Models\ClientInformation;
use App\Models\ClientInformationSnapshot;
use App\Models\Inquiry;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ClientInformationRepositoryInterface;

/**
 * The client's own saved "Client Information" and the copy sent with an
 * inquiry.
 *
 * The saved row holds only how the client wants to get an order and, for a
 * delivery, the site. It is private prefill data, never a CRM `Client` record
 * and never a write to `users` or `user_profiles`. Until a client has saved a
 * row, a new form starts from delivery with no site chosen. An inquiry carries
 * its own snapshot so a later Profile edit cannot rewrite what was sent.
 *
 * Only the event of a save is audited. Neither the old nor the new values are
 * handed to the audit repository: a saved site belongs to the client alone.
 *
 * @phpstan-type ClientInformationInput array{deliveryMethod: string, location: string|null, saveToProfile?: bool}
 */
class ClientInformationService
{
    public function __construct(
        public readonly ClientInformationRepositoryInterface $information,
        public readonly AuditLogRepositoryInterface $auditLog,
    ) {}

    /** The signed-in user's own saved row, or null when there is none. */
    public function savedFor(User $user): ?ClientInformation
    {
        return $this->information->forUser($user);
    }

    /**
     * The values a new form starts from: the saved row when there is one, else
     * delivery with no site. A site no longer on the list is left out silently
     * rather than offered as an answer the server refuses.
     */
    public function prefillFor(User $user): ClientInformationValues
    {
        $saved = $this->savedFor($user);

        if ($saved !== null) {
            return ClientInformationValues::fromSaved($saved)->withValidLocation();
        }

        return new ClientInformationValues(DeliveryMethod::Delivery->value, null);
    }

    /**
     * Create or update the signed-in user's own row, and record only that an
     * event happened. Called inside the same transaction as an inquiry when
     * the client asked to keep their answer for next time.
     *
     * @param  ClientInformationInput  $input
     */
    public function saveForUser(User $user, array $input, ?string $ipAddress, ?string $userAgent): ClientInformation
    {
        $saved = $this->savedFor($user);
        $values = ClientInformationValues::fromInput($input)->withValidLocation();

        $information = $this->information->saveForUser($user, [
            'delivery_method' => $values->deliveryMethod,
            'location' => $values->location,
        ]);

        $this->auditLog->record(
            $user->id,
            $saved === null ? AuditEvent::Created : AuditEvent::Updated,
            $information,
            null,
            null,
            $ipAddress,
            $userAgent,
        );

        return $information;
    }

    /**
     * Copy the delivery answer the client actually sent with one inquiry.
     *
     * @param  ClientInformationInput  $input
     */
    public function snapshotForInquiry(Inquiry $inquiry, array $input): ClientInformationSnapshot
    {
        $values = ClientInformationValues::fromInput($input)->withValidLocation();

        return $this->information->snapshotForInquiry($inquiry, [
            'delivery_method' => $values->deliveryMethod,
            'location' => $values->location,
        ]);
    }
}
