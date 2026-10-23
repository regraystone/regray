<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    /**
     * A page of a conversation's messages, oldest first, removed ones included.
     *
     * With `$before`, the newest `$limit` messages older than it, and `hasMore`
     * says whether anything older is left. With `$after`, the oldest `$limit`
     * messages newer than it, and `hasMore` says whether anything newer is left.
     * With neither, the newest `$limit`.
     *
     * @return array{messages: Collection<int, Message>, hasMore: bool}
     */
    public function page(int $conversationId, ?int $before, ?int $after, int $limit): array;

    public function find(int $id): ?Message;

    /** A message by id, removed ones included. */
    public function findWithTrashed(int $id): ?Message;

    public function findByClientKey(int $conversationId, string $clientKey): ?Message;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Message;

    /**
     * Record the files sent with a message. They are already on the disk.
     *
     * @param  list<array{disk: string, path: string, original_name: string, mime_type: string, size_bytes: int}>  $files
     */
    public function addAttachments(Message $message, array $files): void;

    public function updateBody(Message $message, string $body, CarbonInterface $editedAt): Message;

    public function remove(Message $message): Message;

    /**
     * Post an inquiry card once. Returns null when the same card is already in
     * the conversation, which is what a retried listener will find.
     */
    public function createInquiryCardOnce(int $conversationId, int $inquiryId, string $announcedStatus): ?Message;

    /**
     * Post a schedule card once. Returns null when the same card is already in
     * the conversation, which is what a retried listener will find.
     */
    public function createScheduleCardOnce(int $conversationId, int $scheduleId, string $announcedStatus): ?Message;

    /** Whether the message belongs to the conversation. */
    public function belongsTo(int $messageId, int $conversationId): bool;

    /** Load what a message resource reads. */
    public function loadForDisplay(Message $message): Message;
}
