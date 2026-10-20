<?php

namespace App\Repositories\Eloquent;

use App\Enums\MessageType;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

class EloquentMessageRepository implements MessageRepositoryInterface
{
    /**
     * Everything a message resource reads, in one place, so a page of thirty
     * messages is a fixed number of queries.
     *
     * @var array<int|string, mixed>
     */
    private const DISPLAY_RELATIONS = [
        'sender:id,name',
        'systemUser:id,name',
        'replyTo:id,conversation_id,sender_id,type,body,deleted_at',
        'replyTo.sender:id,name',
        'inquiry:id,reference,status',
        'inquiry.projects:id,inquiry_id,name,sort_order',
        'inquiry.projects.services:id,name',
        'inquiry.projects.stones:id,inquiry_project_id,stone_id,sort_order',
        'inquiry.projects.stones.stone:id,slug,name,thumbnail_path',
        'schedule:id,client_id,booked_by,booked_by_type,inquiry_id,title,arrival_at,status,cancel_reason',
        'schedule.bookedBy:id,name',
        'schedule.inquiry:id,reference',
    ];

    public function page(int $conversationId, ?int $before, ?int $after, int $limit): array
    {
        $query = Message::query()
            ->withTrashed()
            ->where('conversation_id', $conversationId)
            ->with(self::DISPLAY_RELATIONS);

        if ($after !== null) {
            $rows = $query->where('id', '>', $after)->orderBy('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;

            return ['messages' => new Collection($rows->take($limit)->values()->all()), 'hasMore' => $hasMore];
        }

        $rows = $query
            ->when($before !== null, fn (Builder $query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;

        return ['messages' => new Collection($rows->take($limit)->reverse()->values()->all()), 'hasMore' => $hasMore];
    }

    public function find(int $id): ?Message
    {
        return Message::query()->find($id);
    }

    public function findWithTrashed(int $id): ?Message
    {
        return Message::query()->withTrashed()->find($id);
    }

    public function findByClientKey(int $conversationId, string $clientKey): ?Message
    {
        return Message::query()
            ->withTrashed()
            ->where('conversation_id', $conversationId)
            ->where('client_key', $clientKey)
            ->first();
    }

    public function create(array $attributes): Message
    {
        return Message::query()->create([
            'created_at' => Date::now(),
            ...$attributes,
        ]);
    }

    public function updateBody(Message $message, string $body, CarbonInterface $editedAt): Message
    {
        $message->forceFill(['body' => $body, 'edited_at' => $editedAt])->save();

        return $message;
    }

    public function remove(Message $message): Message
    {
        $message->delete();

        return $message;
    }

    public function createInquiryCardOnce(int $conversationId, int $inquiryId, string $announcedStatus): ?Message
    {
        $inserted = Message::query()->insertOrIgnore([
            'conversation_id' => $conversationId,
            'type' => MessageType::InquiryCard->value,
            'inquiry_id' => $inquiryId,
            'announced_status' => $announcedStatus,
            'created_at' => Date::now(),
        ]);

        if ($inserted === 0) {
            return null;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('type', MessageType::InquiryCard->value)
            ->where('inquiry_id', $inquiryId)
            ->where('announced_status', $announcedStatus)
            ->first();
    }

    public function belongsTo(int $messageId, int $conversationId): bool
    {
        return Message::query()->withTrashed()->whereKey($messageId)->where('conversation_id', $conversationId)->exists();
    }

    public function loadForDisplay(Message $message): Message
    {
        return $message->loadMissing(self::DISPLAY_RELATIONS);
    }
}
