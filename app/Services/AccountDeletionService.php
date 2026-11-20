<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deleting one's own account (Account & Security), and whether it can be:
 * an account the studio's records still point to (an inquiry, a booking, a
 * lead or the client record, or for staff an inquiry or lead they handle) is
 * kept, by the database's own foreign keys, and the reader is told why in
 * plain words rather than met with a server error.
 */
class AccountDeletionService
{
    public const CLIENT_MESSAGE = 'Account deletion is currently unavailable because your account has existing inquiries or bookings associated with it. Please contact our support team for assistance.';

    public const STAFF_MESSAGE = 'Account deletion is currently unavailable because your account is linked to inquiries or leads you handle. Please contact an administrator for assistance.';

    /**
     * The SQLSTATEs of a refusal the records cause: a foreign key still
     * pointing at the user, and a check or trigger guarding a linked row.
     */
    private const LINKED_RECORD_STATES = ['23503', '23514'];

    public function __construct(
        public readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Whether the account can be deleted and, when it cannot, what holds it:
     * its inquiries (as the client, a handler or the owner), bookings and
     * leads (as the client or the marketing member holding one), and the
     * message to show. A client record left without any of these still
     * holds it, as the database would.
     *
     * @return array{allowed: bool, audience: 'client'|'staff', inquiries: int, bookings: int, leads: int, message: string|null}
     */
    public function check(User $user): array
    {
        $blockers = $this->users->deletionBlockers($user);
        $inquiries = $blockers['inquiries'] + $blockers['handled'] + $blockers['owned'];
        $leads = $blockers['leads'] + $blockers['heldLeads'];
        $allowed = $inquiries === 0 && $blockers['bookings'] === 0 && $leads === 0 && ! $blockers['clientRecord'];
        $audience = $user->isClient() ? 'client' : 'staff';

        return [
            'allowed' => $allowed,
            'audience' => $audience,
            'inquiries' => $inquiries,
            'bookings' => $blockers['bookings'],
            'leads' => $leads,
            'message' => $allowed ? null : $this->messageFor($audience),
        ];
    }

    /**
     * Delete the account, or say why it cannot be: the reason to show, or
     * null once it is gone. Checked first; should the database still refuse
     * (a record made since), the refusal is reported and the reader gets
     * the same plain message, never the database's.
     */
    public function delete(User $user): ?string
    {
        $check = $this->check($user);

        if (! $check['allowed']) {
            return $check['message'];
        }

        try {
            DB::transaction(fn () => $user->delete());
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), self::LINKED_RECORD_STATES, true)) {
                throw $exception;
            }

            report($exception);

            return $this->messageFor($check['audience']);
        }

        return null;
    }

    /** @param 'client'|'staff' $audience */
    private function messageFor(string $audience): string
    {
        return $audience === 'client' ? self::CLIENT_MESSAGE : self::STAFF_MESSAGE;
    }
}
