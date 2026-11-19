<?php

namespace App\Services;

use App\Data\PhAddress;
use App\Enums\AuditEvent;
use App\Models\ClientAddress;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ClientAddressRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A client's address book (Profile → Addresses).
 *
 * - At most ClientAddress::LIMIT addresses per account.
 * - Exactly one default while there are any: the first one added becomes it,
 *   "Set as default" moves it, and deleting it makes the most recently
 *   changed of the rest the default.
 *
 * Each change runs in a transaction holding the account's row lock, so two
 * tabs cannot add a sixth address or leave two defaults. Only the event of a
 * change is audited, never the address itself: where a client lives is theirs.
 */
class ClientAddressService
{
    public function __construct(
        public readonly ClientAddressRepositoryInterface $addresses,
        public readonly AuditLogRepositoryInterface $auditLog,
    ) {}

    /**
     * @throws ValidationException When the book is full.
     */
    public function add(User $user, string $label, PhAddress $address, ?string $ipAddress, ?string $userAgent): ClientAddress
    {
        return DB::transaction(function () use ($user, $label, $address, $ipAddress, $userAgent): ClientAddress {
            $this->addresses->lockBook($user);
            $count = $this->addresses->countForUser($user);

            if ($count >= ClientAddress::LIMIT) {
                throw ValidationException::withMessages(['address' => self::fullMessage()]);
            }

            $saved = $this->addresses->create($user, [
                'label' => $label,
                ...$address->toColumns(),
                'is_default' => $count === 0,
            ]);

            $this->audit($user, AuditEvent::Created, $saved, $ipAddress, $userAgent);

            return $saved;
        });
    }

    public function update(User $user, ClientAddress $saved, string $label, PhAddress $address, ?string $ipAddress, ?string $userAgent): ClientAddress
    {
        return DB::transaction(function () use ($user, $saved, $label, $address, $ipAddress, $userAgent): ClientAddress {
            $this->addresses->lockBook($user);
            $this->addresses->update($saved, ['label' => $label, ...$address->toColumns()]);
            $this->audit($user, AuditEvent::Updated, $saved, $ipAddress, $userAgent);

            return $saved;
        });
    }

    /** Make it the default; the one that was stops being it. */
    public function makeDefault(User $user, ClientAddress $saved, ?string $ipAddress, ?string $userAgent): void
    {
        DB::transaction(function () use ($user, $saved, $ipAddress, $userAgent): void {
            $this->addresses->lockBook($user);
            $this->addresses->makeDefault($saved);
            $this->audit($user, AuditEvent::Updated, $saved, $ipAddress, $userAgent);
        });
    }

    /**
     * Delete it. When it was the default, the most recently changed of the
     * rest takes its place, so a book with addresses always has one.
     */
    public function remove(User $user, ClientAddress $saved, ?string $ipAddress, ?string $userAgent): void
    {
        DB::transaction(function () use ($user, $saved, $ipAddress, $userAgent): void {
            $this->addresses->lockBook($user);
            $wasDefault = $saved->is_default;

            $this->audit($user, AuditEvent::Deleted, $saved, $ipAddress, $userAgent);
            $this->addresses->delete($saved);

            if ($wasDefault) {
                $next = $this->addresses->mostRecentlyUpdated($user);

                if ($next !== null) {
                    $this->addresses->makeDefault($next);
                }
            }
        });
    }

    public static function fullMessage(): string
    {
        return 'You can keep up to '.ClientAddress::LIMIT.' addresses. Delete one to add another.';
    }

    private function audit(User $user, AuditEvent $event, ClientAddress $address, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditLog->record($user->id, $event, $address, null, null, $ipAddress, $userAgent);
    }
}
