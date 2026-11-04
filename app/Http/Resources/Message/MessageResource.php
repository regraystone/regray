<?php

namespace App\Http\Resources\Message;

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * One message as a thread draws it.
 *
 * Built for a viewer, because two rules depend on who is reading:
 * - On a Regray Team thread nobody is handling, no staff name reaches the
 *   client: a staff sender is shown as "Regray Team".
 * - A visit card is only filled in for staff and for the client whose visit it
 *   is; anyone else gets the card without its contents.
 *
 * Built without a viewer (for a broadcast), neither rule can be applied, so the
 * broadcast only ever carries text and system messages; cards are announced by
 * id and fetched by each viewer.
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    public const SNIPPET_LENGTH = 120;

    public const SUPPORT_TEAM_NAME = 'Regray Team';

    /** What a reply quotes when the message it answers is files only. */
    public const ATTACHMENT_SNIPPET = 'Attachment';

    public function __construct(
        Message $message,
        public readonly Conversation $conversation,
        public readonly ?User $viewer = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $removed = $this->trashed();

        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'type' => $this->type->value,
            'body' => $removed || $this->type !== MessageType::Text ? null : $this->body,
            'isRemoved' => $removed,
            'isEdited' => $this->edited_at !== null && ! $removed,
            'createdAt' => $this->created_at->toIso8601String(),
            'clientKey' => $this->viewer === null || $this->sender_id === $this->viewer->id ? $this->client_key : null,
            'sender' => $this->sender === null ? null : [
                'id' => $this->sender->id,
                'name' => $this->senderName($this->resource),
            ],
            'replyTo' => $this->replyTo === null ? null : [
                'id' => $this->replyTo->id,
                'senderName' => $this->replyTo->sender === null ? null : $this->senderName($this->replyTo),
                'snippet' => match (true) {
                    $this->replyTo->trashed() || $this->replyTo->body === null => null,
                    $this->replyTo->body === '' => self::ATTACHMENT_SNIPPET,
                    default => Str::limit($this->replyTo->body, self::SNIPPET_LENGTH),
                },
                'isRemoved' => $this->replyTo->trashed(),
            ],
            'inquiryCard' => $this->type === MessageType::InquiryCard && $this->inquiry !== null
                ? (new InquiryCardResource($this->resource))->resolve($request)
                : null,
            'scheduleCard' => $this->type === MessageType::ScheduleCard && $this->mayReadSchedule()
                ? (new ScheduleCardResource($this->resource))->resolve($request)
                : null,
            'attachments' => $removed || ! $this->relationLoaded('attachments') ? [] : $this->attachments
                ->map(fn (MessageAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mimeType' => $attachment->mime_type,
                    'size' => $attachment->size_bytes,
                    'isImage' => str_starts_with($attachment->mime_type, 'image/'),
                    'url' => route('inbox.attachments.show', $attachment, absolute: false),
                ])
                ->values()
                ->all(),
            'systemEvent' => $this->type === MessageType::System ? [
                'event' => $this->system_event?->value,
                'userName' => $this->systemUser?->name,
            ] : null,
        ];
    }

    /**
     * The name a viewer is shown for a sender. Before a Regray Team thread is
     * claimed, the client sees the team rather than a person.
     */
    private function senderName(Message $message): string
    {
        $sender = $message->sender;

        if ($sender === null) {
            return self::SUPPORT_TEAM_NAME;
        }

        $hidesStaff = $this->viewer !== null
            && $this->conversation->isSupport()
            && $this->conversation->assigned_to === null
            && $this->viewer->id === $this->conversation->support_client_id
            && $sender->id !== $this->conversation->support_client_id;

        return $hidesStaff ? self::SUPPORT_TEAM_NAME : $sender->name;
    }

    private function mayReadSchedule(): bool
    {
        if ($this->schedule === null || $this->viewer === null) {
            return false;
        }

        return ! $this->viewer->isClient() || $this->schedule->client_id === $this->viewer->id;
    }
}
