<?php

namespace App\Services;

use App\Enums\ContactKind;
use App\Models\Client;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Client records: a `platform` client has a Regray account, a `direct`
 * (walk-in) client does not and is taken down by staff.
 *
 * Two records are never merged. An account gets one record, made the first
 * time it is needed (findOrCreateForUser()); a direct client is not created
 * when one of its contacts is another client's, and that client is offered
 * instead (createDirect()).
 */
class ClientService
{
    public const ACCOUNT_SEARCH_LIMIT = 20;

    public function __construct(
        public readonly ClientRepositoryInterface $clients,
    ) {}

    /**
     * The account's client record, created as `platform` the first time.
     * Safe to call from two requests at once: the unique `user_id` turns the
     * second insert into a lookup.
     */
    public function findOrCreateForUser(User $user): Client
    {
        $client = $this->clients->findForUser($user);

        if ($client !== null) {
            return $client;
        }

        try {
            // A savepoint, so a lost race does not abort the caller's transaction.
            return DB::transaction(fn (): Client => $this->clients->createForUser($user));
        } catch (UniqueConstraintViolationException) {
            return $this->clients->findForUser($user) ?? throw new RuntimeException('The client record could not be created.');
        }
    }

    /**
     * Take down a direct (walk-in) client, or find the one already there:
     * when one of the contacts given is another client's, nothing is created
     * and that client comes back with `existing` true, for staff to use
     * instead.
     *
     * @param  array{full_name: string, phone: string, email: ?string}  $input
     * @return array{client: Client, existing: bool, matched: array{kind: string, value: string}|null}
     *
     * @throws ValidationException When the same contact is given twice.
     */
    public function createDirect(array $input, User $by): array
    {
        $contacts = $this->normalisedContacts([
            ['kind' => ContactKind::Phone->value, 'value' => $input['phone']],
            ...($this->blankToNull($input['email']) === null ? [] : [['kind' => ContactKind::Email->value, 'value' => (string) $input['email']]]),
        ]);
        $existing = $this->clients->findByContacts($contacts);

        if ($existing !== null) {
            return $this->existing($existing);
        }

        $attributes = [
            'full_name' => trim($input['full_name']),
        ];

        try {
            $client = DB::transaction(fn (): Client => $this->clients->createDirect($attributes, $contacts, $by->id));
        } catch (UniqueConstraintViolationException) {
            // Somebody took the same contact down a moment ago.
            return $this->existing($this->clients->findByContacts($contacts) ?? throw new RuntimeException('The client could not be created.'));
        }

        return ['client' => $client, 'existing' => false, 'matched' => null];
    }

    /**
     * Client accounts `$viewer` may find (User::scopeClientsVisibleTo())
     * matching a search, for the Platform branch of the staff Inquire flow
     * and the staff Book an appointment page. The admin and the owner find
     * every client; anyone else, only the clients they are authorized for.
     *
     * @return Collection<int, User>
     */
    public function searchAccounts(User $viewer, ?string $search): Collection
    {
        $search = $search === null ? '' : trim($search);

        return $this->clients->searchAccounts($viewer, str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search), self::ACCOUNT_SEARCH_LIMIT);
    }

    /**
     * The client's phone number, the lists' Contact column: a direct client's
     * phone number, or the account's phone.
     */
    public static function phone(?Client $record, ?User $account): ?string
    {
        if ($record !== null && $record->user_id === null) {
            return $record->contacts->firstWhere('kind', ContactKind::Phone)?->value;
        }

        return $account?->profile?->phone;
    }

    /** The client's email, the lists' Email column: a direct client's first email, or the account's. */
    public static function email(?Client $record, ?User $account): ?string
    {
        if ($record !== null && $record->user_id === null) {
            return $record->contacts->firstWhere('kind', ContactKind::Email)?->value;
        }

        return $account?->email;
    }

    /**
     * @param  array{client: Client, kind: ContactKind, value: string}  $match
     * @return array{client: Client, existing: bool, matched: array{kind: string, value: string}}
     */
    private function existing(array $match): array
    {
        return ['client' => $match['client'], 'existing' => true, 'matched' => ['kind' => $match['kind']->value, 'value' => $match['value']]];
    }

    /**
     * @param  list<array{kind: string, value: string}>  $contacts
     * @return list<array{kind: ContactKind, value: string}>
     *
     * @throws ValidationException
     */
    private function normalisedContacts(array $contacts): array
    {
        $normalised = [];
        $seen = [];

        foreach ($contacts as $contact) {
            $kind = ContactKind::from($contact['kind']);
            $value = $kind->normalise($contact['value']);
            $key = "{$kind->value}:{$value}";

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([$kind === ContactKind::Email ? 'email' : 'phone' => 'This contact is already on the list.']);
            }

            $seen[$key] = true;
            $normalised[] = ['kind' => $kind, 'value' => $value];
        }

        return $normalised;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
