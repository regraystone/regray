<?php

namespace App\Services\Message;

use App\Data\Message\ConversationListFilters;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reading the Inbox page: the conversation list and a thread's messages.
 */
class ConversationService
{
    public const PER_PAGE = 20;

    public const THREAD_PAGE = 30;

    public const MAX_THREAD_PAGE = 100;

    /** What `?conversation=` takes to mean "my Regray Team thread". */
    public const SUPPORT_ALIAS = 'support';

    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
        public readonly MessageRepositoryInterface $messages,
        public readonly SupportConversationService $support,
        public readonly InquiryRepositoryInterface $inquiries,
    ) {}

    /**
     * One page of the user's conversations. A client's Regray Team thread is
     * created here the first time they open Message, so it is always on top.
     *
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function list(User $user, ConversationListFilters $filters): LengthAwarePaginator
    {
        if ($user->isClient()) {
            $this->support->ensureFor($user);
        }

        $page = $this->conversations->paginateForUser(
            $user,
            $filters,
            $this->escapeSearch($filters->search),
            includeSupportPool: $this->listsSupportPool($user),
            includeAllSupport: $this->listsAllSupport($user),
            perPage: self::PER_PAGE,
        );

        $this->attachLastMessages($page->getCollection());

        return $page;
    }

    /**
     * The unread messages across the user's whole list, for the badge on the
     * Message link. Counted over exactly the conversations list() shows.
     */
    public function unreadTotal(User $user): int
    {
        return $this->conversations->unreadTotalFor(
            $user,
            $this->listsSupportPool($user),
            $this->listsAllSupport($user),
        );
    }

    /** Whether the list holds the unassigned Regray Team threads. */
    private function listsSupportPool(User $user): bool
    {
        return ! $user->isClient() && $user->can(Permission::SupportClaim->value);
    }

    /** Whether the list holds every Regray Team thread. */
    private function listsAllSupport(User $user): bool
    {
        return $user->hasRole(Role::superAdmin()->value);
    }

    /**
     * The conversation `?conversation=` names, or null when there is none.
     * `support` is a client's own Regray Team thread, so a link can open it
     * without knowing its id.
     */
    public function requested(User $user, ?string $value): ?Conversation
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === self::SUPPORT_ALIAS) {
            return $user->isClient() ? $this->support->ensureFor($user) : null;
        }

        return ctype_digit($value) ? $this->conversations->find((int) $value) : null;
    }

    /** The list row for one conversation, to draw its header. */
    public function summary(User $user, Conversation $conversation): Conversation
    {
        $row = $this->conversations->findSummaryForUser($user, $conversation->id) ?? $conversation;
        $this->attachLastMessages(new Collection([$row]));

        return $row;
    }

    /**
     * A page of messages, who has read how far, and the assignment state.
     *
     * A pool member opening an unassigned Regray Team thread is given a viewer
     * row the first time, so their reading is remembered; it goes again when
     * somebody claims the thread.
     *
     * @return array{messages: Collection<int, Message>, hasMore: bool, pointers: list<array{userId: int, name: string, lastReadMessageId: int|null, lastDeliveredMessageId: int|null}>, assignment: array<string, mixed>|null, canRequestQuote: bool}
     */
    public function thread(User $user, Conversation $conversation, ?int $before, ?int $after, ?int $limit): array
    {
        if ($conversation->isSupport() && $conversation->assigned_to === null && $conversation->support_client_id !== $user->id) {
            $this->members->ensureViewer($conversation->id, $user->id);
        }

        $page = $this->messages->page(
            $conversation->id,
            $before,
            $after,
            min(max($limit ?? self::THREAD_PAGE, 1), self::MAX_THREAD_PAGE),
        );

        return [
            'messages' => $page['messages'],
            'hasMore' => $page['hasMore'],
            'pointers' => $this->pointers($conversation),
            'assignment' => $this->assignmentFor($user, $conversation),
            'canRequestQuote' => $this->canRequestQuote($user, $conversation),
        ];
    }

    /**
     * Whether this viewer's thread offers the "Request a quotation" button: only
     * their own Regray Team thread, only if they may send an inquiry, and only
     * while they have none going.
     */
    public function canRequestQuote(User $user, Conversation $conversation): bool
    {
        return $conversation->isSupport()
            && $conversation->support_client_id === $user->id
            && $user->can(Permission::InquiriesCreate->value)
            && ! $this->inquiries->clientHasActive($user);
    }

    /**
     * Read and delivered pointers of the members who take part. Pool viewers
     * are left out: a client must not learn which staff opened their thread.
     *
     * @return list<array{userId: int, name: string, lastReadMessageId: int|null, lastDeliveredMessageId: int|null}>
     */
    public function pointers(Conversation $conversation): array
    {
        return array_values($this->members->activeMembers($conversation->id)
            ->map(fn (ConversationMember $member): array => [
                'userId' => $member->user_id,
                'name' => $member->user->name,
                'lastReadMessageId' => $member->last_read_message_id,
                'lastDeliveredMessageId' => $member->last_delivered_message_id,
            ])
            ->all());
    }

    /**
     * The staff-only assignment state of a Regray Team thread. Null for a
     * client and for every other kind of conversation, so nothing about the
     * pool ever reaches a client.
     *
     * @return array{canClaim: bool, assignee: array{id: int, name: string}|null, isMine: bool, canRelease: bool, canReassign: bool}|null
     */
    public function assignmentFor(User $user, Conversation $conversation): ?array
    {
        if (! $conversation->isSupport() || $user->isClient()) {
            return null;
        }

        $conversation->loadMissing('assignee:id,name');
        $isAdmin = $user->hasRole(Role::superAdmin()->value);
        $assignee = $conversation->assignee;

        return [
            'canClaim' => $assignee === null && $user->can(Permission::SupportClaim->value),
            'assignee' => $assignee === null ? null : ['id' => $assignee->id, 'name' => $assignee->name],
            'isMine' => $assignee !== null && $assignee->id === $user->id,
            'canRelease' => $assignee !== null && ($assignee->id === $user->id || $isAdmin),
            'canReassign' => $isAdmin,
        ];
    }

    /**
     * A search term made safe for a LIKE pattern, as the quote search does it.
     */
    public function escapeSearch(?string $search): ?string
    {
        $search = $search === null ? '' : trim($search);

        return $search === '' ? null : str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Conversation>  $conversations
     */
    private function attachLastMessages(\Illuminate\Support\Collection $conversations): void
    {
        $ids = array_values(array_filter(
            $conversations->map(fn (Conversation $conversation): ?int => $conversation->getAttribute('last_message_id') === null
                ? null
                : (int) $conversation->getAttribute('last_message_id'))->all(),
        ));

        $messages = $this->conversations->previewMessages($ids)->keyBy('id');

        foreach ($conversations as $conversation) {
            $conversation->setRelation('lastMessage', $messages->get((int) $conversation->getAttribute('last_message_id')));
        }
    }
}
