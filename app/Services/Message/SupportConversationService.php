<?php

namespace App\Services\Message;

use App\Enums\ConversationMemberRole;
use App\Enums\MessageType;
use App\Enums\SystemEvent;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Schedule;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The Regray Team thread: one per client, global to the support pool until one
 * of them claims it, and then that person's alone, for good: a claim is never
 * released or reassigned.
 *
 * Every method that changes the assignment expects to run inside a transaction,
 * which the calling action owns: the row is locked first, so two claims at once
 * produce one winner.
 */
class SupportConversationService
{
    public const MESSAGE_ASSIGN_FIRST = 'Assign this conversation to yourself first.';

    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
        public readonly MessageRepositoryInterface $messages,
    ) {}

    /**
     * The client's Regray Team thread, created the first time it is needed.
     *
     * Safe to call from two requests at once: the unique key on
     * `support_client_id` turns the second insert into a lookup.
     */
    public function ensureFor(User $client): Conversation
    {
        $conversation = $this->conversations->findSupportFor($client);

        if ($conversation === null) {
            try {
                // A savepoint, so a lost race does not abort an enclosing transaction.
                $conversation = DB::transaction(fn (): Conversation => $this->conversations->createSupportFor($client));
            } catch (UniqueConstraintViolationException) {
                $conversation = $this->conversations->findSupportFor($client)
                    ?? throw new \RuntimeException('The Regray Team thread could not be created.');
            }
        }

        $this->members->upsert($conversation->id, $client->id, ConversationMemberRole::Member);

        return $conversation;
    }

    /**
     * Where an inquiry's cards go: its group chat once marketing has made one
     * for it, otherwise its client's Regray Team thread, created if the client
     * has none yet. Null for a direct (walk-in) client's inquiry with no group
     * chat: there is no account to have a thread.
     */
    public function cardThreadFor(Inquiry $inquiry): ?Conversation
    {
        $group = $this->conversations->groupForInquiry($inquiry->id);

        if ($group !== null || $inquiry->client_id === null) {
            return $group;
        }

        return $this->ensureFor(User::query()->findOrFail($inquiry->client_id));
    }

    /**
     * Where a schedule's cards go: its client's Regray Team thread, created if
     * the client has none yet.
     *
     * The one place that picks the thread. A booking has no inquiry, so it has
     * no group chat yet; the group-chat rule goes here when it does.
     */
    public function scheduleCardThreadFor(Schedule $schedule): Conversation
    {
        return $this->ensureFor($schedule->loadMissing('client')->client);
    }

    /**
     * Whether the user may write in the thread now. Access is the policy's;
     * this answers the assignment question with 409.
     */
    public function assertCanSend(User $user, Conversation $conversation): void
    {
        if (! $conversation->isSupport() || $conversation->support_client_id === $user->id) {
            return;
        }

        if ($conversation->assigned_to === null) {
            throw new ConflictHttpException(self::MESSAGE_ASSIGN_FIRST);
        }

        if ($conversation->assigned_to !== $user->id) {
            $name = $conversation->assignee->name ?? 'Someone else';

            throw new ConflictHttpException("{$name} is handling this conversation.");
        }
    }

    /**
     * Take the thread from the pool. Only one claim can win: the update only
     * matches a thread nobody holds, and the loser is told who got there first.
     */
    public function claim(User $user, Conversation $conversation): Message
    {
        $locked = $this->conversations->lockForUpdate($conversation->id);

        if (! $this->conversations->assignIfUnassigned($locked->id, $user->id, Date::now())) {
            $name = $locked->assignee->name ?? 'Someone else';

            throw new ConflictHttpException("{$name} already took this conversation.");
        }

        $this->members->upsert($locked->id, $user->id, ConversationMemberRole::Member);
        $this->members->deleteViewers($locked->id);

        return $this->systemMessage($locked->id, SystemEvent::Assigned, $user);
    }

    private function systemMessage(int $conversationId, SystemEvent $event, User $about): Message
    {
        $message = $this->messages->create([
            'conversation_id' => $conversationId,
            'type' => MessageType::System,
            'system_event' => $event,
            'system_user_id' => $about->id,
        ]);

        $this->conversations->touch($conversationId);

        return $this->messages->loadForDisplay($message);
    }
}
