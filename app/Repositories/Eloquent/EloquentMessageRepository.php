<?php

namespace App\Repositories\Eloquent;

use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
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
        'attachments:id,message_id,original_name,mime_type,size_bytes',
        'systemUser:id,name',
        'replyTo:id,conversation_id,sender_id,type,body,deleted_at',
        'replyTo.sender:id,name',
        'inquiry:id,reference,lead_id,marketing_status,name,location,stone_advice_requested,sales_status,payment_status,process_status',
        'inquiry.stones:id,inquiry_id,stone_id,sort_order',
        'inquiry.stones.stone:id,slug,name,thumbnail_path',
        'inquiry.files:id,inquiry_id,document_type,deleted_at',
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

    public function addAttachments(Message $message, array $files): void
    {
        foreach ($files as $file) {
            $message->attachments()->create($file);
        }
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

    public function createInquiryCardOnce(
        int $conversationId,
        int $inquiryId,
        InquiryStatus $status,
        ?SalesStatus $salesStatus,
        ?PaymentStatus $paymentStatus,
        ?ProcessStatus $processStatus,
        ?CarbonInterface $postedAt = null,
    ): ?Message {
        $snapshot = [
            'conversation_id' => $conversationId,
            'type' => MessageType::InquiryCard->value,
            'inquiry_id' => $inquiryId,
            'announced_status' => $status->value,
            'announced_sales_status' => $salesStatus?->value,
            'announced_payment_status' => $paymentStatus?->value,
            'announced_process_status' => $processStatus?->value,
        ];

        $inserted = Message::query()->insertOrIgnore([
            ...$snapshot,
            // In the app's zone, which the session reads it in: a query-builder
            // insert writes the date without its offset.
            'created_at' => Date::instance($postedAt ?? Date::now())->setTimezone(config('app.timezone')),
        ]);

        if ($inserted === 0) {
            return null;
        }

        return Message::query()->where($snapshot)->first();
    }

    public function createScheduleCardOnce(int $conversationId, int $scheduleId, string $announcedStatus): ?Message
    {
        $inserted = Message::query()->insertOrIgnore([
            'conversation_id' => $conversationId,
            'type' => MessageType::ScheduleCard->value,
            'schedule_id' => $scheduleId,
            'announced_status' => $announcedStatus,
            'created_at' => Date::now(),
        ]);

        if ($inserted === 0) {
            return null;
        }

        return Message::query()
            ->where('conversation_id', $conversationId)
            ->where('type', MessageType::ScheduleCard->value)
            ->where('schedule_id', $scheduleId)
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
