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
use App\Services\Notification\MessageNotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

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
        public readonly MessageNotificationService $messageNotifications,
    ) {}

    /**
     * Send a text message, with or without files. A message of files alone has
     * an empty body.
     *
     * `$clientKey` is the sender's own id for this send. A retry with the same
     * key returns the message the first attempt created instead of a second
     * copy, so a send that timed out after it landed is not posted twice, and
     * its files are not stored twice.
     *
     * @param  list<UploadedFile>  $files
     * @return array{message: Message, created: bool}
     */
    public function send(User $sender, Conversation $conversation, string $body, ?int $replyToId, string $clientKey, array $files = []): array
    {
        $conversation->loadMissing('assignee:id,name');
        $this->support->assertCanSend($sender, $conversation);

        $existing = $this->messages->findByClientKey($conversation->id, $clientKey);

        if ($existing !== null) {
            return ['message' => $this->messages->loadForDisplay($existing), 'created' => false];
        }

        $stored = $this->storeAttachments($conversation, $files);

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $body, $replyToId, $clientKey, $stored): Message {
                $message = $this->messages->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'type' => MessageType::Text,
                    'body' => $body,
                    'reply_to_id' => $replyToId,
                    'client_key' => $clientKey,
                ]);
                $this->messages->addAttachments($message, $stored);

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            $this->deleteAttachments($stored);
            $message = $this->messages->findByClientKey($conversation->id, $clientKey)
                ?? throw new RuntimeException('The message could not be sent.');

            return ['message' => $this->messages->loadForDisplay($message), 'created' => false];
        } catch (Throwable $exception) {
            $this->deleteAttachments($stored);

            throw $exception;
        }

        $this->members->moveDeliveredPointer($conversation->id, $sender->id, $message->id);
        $this->members->moveReadPointer($conversation->id, $sender->id, $message->id);
        $this->conversations->touch($conversation->id);

        $message = $this->messages->loadForDisplay($message);

        broadcast(new MessageCreated($message, $conversation))->toOthers();
        $this->announceActivity($conversation, 'message', $message->id);
        $this->messageNotifications->messageSent($message, $conversation, $sender);

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
     * Reading the thread cancels the user's message notification for it.
     */
    public function markRead(User $user, Conversation $conversation, int $messageId): void
    {
        $this->messageNotifications->closeFor($user, $conversation);
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
    public function announceActivity(Conversation $conversation, string $reason = 'message', ?int $lastMessageId = null): void
    {
        event(new ConversationActivity(
            $conversation->id,
            $this->conversations->memberUserIds($conversation->id),
            toSupportPool: $conversation->isSupport() && $conversation->assigned_to === null,
            reason: $reason,
            lastMessageId: $lastMessageId,
        ));
    }

    /**
     * Put the files on the attachments disk under messages/{conversation id}/.
     * The stored name is generated: the sender's own file name is kept only as
     * `original_name`, never as a path. If one file fails, the ones already
     * written are removed again.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{disk: string, path: string, original_name: string, mime_type: string, size_bytes: int}>
     */
    private function storeAttachments(Conversation $conversation, array $files): array
    {
        $disk = (string) config('messaging.attachments.disk');
        $stored = [];

        try {
            foreach ($files as $file) {
                // From the bytes, not the name: a PDF sent as `x.html` is stored as `.pdf`.
                $extension = strtolower((string) $file->guessExtension());
                $name = Str::uuid()->toString().($extension === '' ? '' : '.'.$extension);
                $path = Storage::disk($disk)->putFileAs("messages/{$conversation->id}", $file, $name);

                if ($path === false) {
                    throw new RuntimeException("Could not store {$file->getClientOriginalName()}.");
                }

                $stored[] = [
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                    'size_bytes' => (int) $file->getSize(),
                ];
            }
        } catch (Throwable $exception) {
            $this->deleteAttachments($stored);

            throw $exception;
        }

        return $stored;
    }

    /** @param  list<array{disk: string, path: string}>  $stored */
    private function deleteAttachments(array $stored): void
    {
        foreach ($stored as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
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
