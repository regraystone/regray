<?php

namespace App\Services\Message;

use App\Enums\ConversationMemberRole;
use App\Enums\MessageType;
use App\Events\Message\ConversationActivity;
use App\Events\Message\MessageCreated;
use App\Events\Message\MessageUpdated;
use App\Events\Message\PointerMoved;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Writing in a thread: sending, editing, removing, and the read and delivered
 * pointers behind the receipts.
 */
class MessageService
{
    public function __construct(
        public readonly ConversationRepositoryInterface $conversations,
        public readonly ConversationMemberRepositoryInterface $members,
        public readonly MessageRepositoryInterface $messages,
        public readonly SupportConversationService $support,
    ) {}

    /**
     * Send a text message.
     *
     * `$clientKey` is the sender's own id for this send. A retry with the same
     * key returns the message the first attempt created instead of a second
     * copy, so a send that timed out after it landed is not posted twice.
     *
     * @return array{message: Message, created: bool}
     */
    public function send(User $sender, Conversation $conversation, string $body, ?int $replyToId, string $clientKey): array
    {
        $conversation->loadMissing('assignee:id,name');
        $this->support->assertCanSend($sender, $conversation);

        $existing = $this->messages->findByClientKey($conversation->id, $clientKey);

        if ($existing !== null) {
            return ['message' => $this->messages->loadForDisplay($existing), 'created' => false];
        }

        try {
            $message = DB::transaction(fn (): Message => $this->messages->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => MessageType::Text,
                'body' => $body,
                'reply_to_id' => $replyToId,
                'client_key' => $clientKey,
            ]));
        } catch (UniqueConstraintViolationException) {
            $message = $this->messages->findByClientKey($conversation->id, $clientKey)
                ?? throw new \RuntimeException('The message could not be sent.');

            return ['message' => $this->messages->loadForDisplay($message), 'created' => false];
        }

        $this->members->moveDeliveredPointer($conversation->id, $sender->id, $message->id);
        $this->members->moveReadPointer($conversation->id, $sender->id, $message->id);
        $this->conversations->touch($conversation->id);

        $message = $this->messages->loadForDisplay($message);

        broadcast(new MessageCreated($message, $conversation))->toOthers();
        $this->announceActivity($conversation);

        return ['message' => $message, 'created' => true];
    }

    /** Change the words of one's own text message. */
    public function edit(User $user, Message $message, string $body, Conversation $conversation): Message
    {
        $this->assertOwnText($user, $message);

        $message = $this->messages->updateBody($message, $body, Date::now());
        $message = $this->messages->loadForDisplay($message);

        broadcast(new MessageUpdated($message, $conversation))->toOthers();

        return $message;
    }

    /** Remove one's own message: everybody sees "This message was removed". */
    public function remove(User $user, Message $message, Conversation $conversation): Message
    {
        $this->assertOwnText($user, $message);

        $message = $this->messages->remove($message);
        $message = $this->messages->loadForDisplay($message);

        broadcast(new MessageUpdated($message, $conversation))->toOthers();
        $this->announceActivity($conversation);

        return $message;
    }

    /**
     * Move the user's read pointer, and the delivered one with it: a message
     * that has been read has been delivered. Pointers only move forward.
     */
    public function markRead(User $user, Conversation $conversation, int $messageId): void
    {
        $this->ensurePointerRow($user, $conversation);

        $delivered = $this->members->moveDeliveredPointer($conversation->id, $user->id, $messageId);
        $read = $this->members->moveReadPointer($conversation->id, $user->id, $messageId);

        if ($read || $delivered) {
            $this->announcePointer($user, $conversation);
        }
    }

    public function markDelivered(User $user, Conversation $conversation, int $messageId): void
    {
        $this->ensurePointerRow($user, $conversation);

        if ($this->members->moveDeliveredPointer($conversation->id, $user->id, $messageId)) {
            $this->announcePointer($user, $conversation);
        }
    }

    /**
     * Tell every list that shows this conversation that it changed. An
     * unassigned Regray Team thread is in every pool member's list too.
     */
    public function announceActivity(Conversation $conversation, string $reason = 'message'): void
    {
        event(new ConversationActivity(
            $conversation->id,
            $this->conversations->memberUserIds($conversation->id),
            toSupportPool: $conversation->isSupport() && $conversation->assigned_to === null,
            reason: $reason,
        ));
    }

    /** Only the sender may change or remove a message, and only a text one. */
    private function assertOwnText(User $user, Message $message): void
    {
        if ($message->sender_id !== $user->id) {
            throw new AccessDeniedHttpException('You can only change your own messages.');
        }

        if ($message->type !== MessageType::Text || $message->trashed()) {
            throw new UnprocessableEntityHttpException('This message can no longer be changed.');
        }
    }

    /**
     * A pool member reading an unassigned Regray Team thread has no row yet;
     * give them a viewer row to hold the pointer.
     */
    private function ensurePointerRow(User $user, Conversation $conversation): void
    {
        if ($conversation->isSupport() && $conversation->assigned_to === null && $conversation->support_client_id !== $user->id) {
            $this->members->ensureViewer($conversation->id, $user->id);
        }
    }

    /** Receipts are only announced for members who take part, never viewers. */
    private function announcePointer(User $user, Conversation $conversation): void
    {
        $member = $this->members->find($conversation->id, $user->id);

        if ($member === null || $member->role === ConversationMemberRole::Viewer) {
            return;
        }

        broadcast(new PointerMoved(
            $conversation->id,
            $user->id,
            $user->name,
            $member->last_read_message_id,
            $member->last_delivered_message_id,
        ))->toOthers();
    }
}
