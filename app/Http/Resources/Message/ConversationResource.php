<?php

namespace App\Http\Resources\Message;

use App\Enums\ConversationType;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\SystemEvent;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Repositories\Eloquent\EloquentConversationRepository;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A conversation as a row in the list and as a thread header.
 *
 * The name depends on who is reading. A client's own Regray Team thread is
 * "Regray"; staff see the same thread by the client's name, because a pool
 * of identical "Regray" rows would be unreadable. A direct conversation is
 * named after the other person.
 *
 * Expects the columns and relations EloquentConversationRepository::listQuery
 * selects, and the latest message set as the `lastMessage` relation.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    public const PREVIEW_LENGTH = 80;

    public const EMPTY_SUPPORT_PREVIEW = 'Message Regray any time.';

    /** @var array<string, string> */
    private const SCHEDULE_HEADLINES = [
        'pending' => 'Booking pending',
        'confirmed' => 'Booking approved',
        'rescheduled' => 'Appointment rescheduled',
        'served' => 'Appointment served',
        'cancelled' => 'Booking cancelled',
        'expired' => 'Booking expired',
    ];

    /** Who is reading: set with `viewedBy()`, otherwise the signed-in user. */
    private ?User $viewer = null;

    public function viewedBy(User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->viewer ??= $request->user();
        $isOwnSupport = $this->isSupport() && $this->support_client_id === $this->viewer->id;
        $viewerIsClient = $this->viewer->isClient();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->displayName($isOwnSupport),
            'avatar' => $this->avatar($viewerIsClient),
            'isSupport' => $this->isSupport(),
            'isOwnSupport' => $isOwnSupport,
            'unreadCount' => (int) ($this->getAttribute('unread_count') ?? 0),
            'lastActivityAt' => CarbonImmutable::parse($this->getAttribute('last_activity_at') ?? $this->created_at)->toIso8601String(),
            'preview' => $this->preview($isOwnSupport),
            'assignee' => $this->isSupport() && $this->assignee !== null
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name]
                : null,
            'supportClient' => $this->isSupport() && ! $viewerIsClient && $this->supportClient !== null
                ? ['id' => $this->supportClient->id, 'name' => $this->supportClient->name]
                : null,
            'canClaim' => $this->isSupport()
                && ! $viewerIsClient
                && $this->assigned_to === null
                && $this->viewer->can(Permission::SupportClaim->value),
            'memberCount' => $this->type === ConversationType::Group ? $this->members->count() : null,
            'canAddMembers' => $this->canAddMembers($viewerIsClient),
        ];
    }

    /**
     * Whether the reader may add accounting or logistics to this group: a sales
     * member of an inquiry's group chat. The same answer as
     * ConversationPolicy::addMembers, read off the loaded members.
     */
    private function canAddMembers(bool $viewerIsClient): bool
    {
        return $this->type === ConversationType::Group
            && $this->inquiry_id !== null
            && ! $viewerIsClient
            && $this->viewer->hasRole(Role::Sales->value)
            && $this->members->contains(fn (ConversationMember $member): bool => $member->user_id === $this->viewer->id);
    }

    /**
     * A client sees their Regray Team thread with the Regray logo. Staff see
     * each client's thread as that client, under the client's name and with
     * no picture, so the page draws the client's own initials and every
     * client's thread is told apart at a glance rather than all wearing the
     * same logo.
     */
    private function avatar(bool $viewerIsClient): ?string
    {
        if (! $this->isSupport()) {
            return $this->avatar_path;
        }

        return $viewerIsClient ? EloquentConversationRepository::SUPPORT_AVATAR : null;
    }

    private function displayName(bool $isOwnSupport): string
    {
        if ($this->isSupport()) {
            return $isOwnSupport || $this->viewer->isClient()
                ? EloquentConversationRepository::SUPPORT_NAME
                : ($this->supportClient->name ?? EloquentConversationRepository::SUPPORT_NAME);
        }

        if ($this->type === ConversationType::Direct) {
            $other = $this->members->first(fn (ConversationMember $member): bool => $member->user_id !== $this->viewer->id);

            return $other?->user->name ?? (string) $this->name;
        }

        return (string) $this->name;
    }

    /**
     * One line about the latest message. Staff names stay out of a client's
     * Regray Team preview until somebody is handling the thread.
     */
    private function preview(bool $isOwnSupport): string
    {
        /** @var Message|null $message */
        $message = $this->relationLoaded('lastMessage') ? $this->getRelation('lastMessage') : null;

        if ($message === null) {
            return $isOwnSupport ? self::EMPTY_SUPPORT_PREVIEW : 'No messages yet';
        }

        if ($message->trashed()) {
            return 'Message removed';
        }

        return match ($message->type) {
            MessageType::Text => $this->textPreview($message, $isOwnSupport),
            MessageType::InquiryCard => $this->inquiryPreview($message),
            MessageType::ScheduleCard => (self::SCHEDULE_HEADLINES[(string) $message->announced_status] ?? 'Appointment').' · '.($message->schedule->title ?? ''),
            MessageType::System => $this->systemPreview($message),
        };
    }

    /**
     * An inquiry card's line. A pending card is the one a newly sent inquiry
     * posts, so it reads as the card's own headline does.
     */
    private function inquiryPreview(Message $message): string
    {
        $reference = $message->inquiry->reference ?? '';

        if ($message->announced_status === InquiryStatus::Pending->value) {
            return "New Inquiry · {$reference}";
        }

        return "Inquiry {$reference} · ".Str::headline((string) $message->announced_status);
    }

    private function textPreview(Message $message, bool $isOwnSupport): string
    {
        $body = $message->body === '' && $message->relationLoaded('attachments')
            ? $this->attachmentPreview($message)
            : Str::limit(Str::squish((string) $message->body), self::PREVIEW_LENGTH);

        if ($message->sender_id === $this->viewer->id) {
            return "You: {$body}";
        }

        if ($isOwnSupport && $this->assigned_to === null) {
            return $body;
        }

        if ($message->sender !== null && ($this->type === ConversationType::Group || $this->isSupport())) {
            return Str::before($message->sender->name, ' ').": {$body}";
        }

        return $body;
    }

    /** A message of files alone: "Sent a photo", "Sent 3 files". */
    private function attachmentPreview(Message $message): string
    {
        $count = $message->attachments->count();
        $photos = $message->attachments->every(fn (MessageAttachment $attachment): bool => str_starts_with($attachment->mime_type, 'image/'));
        $noun = $photos ? 'photo' : 'file';

        return $count === 1 ? "Sent a {$noun}" : "Sent {$count} ".Str::plural($noun);
    }

    private function systemPreview(Message $message): string
    {
        $name = $message->systemUser->name ?? 'Someone';

        return match ($message->system_event) {
            SystemEvent::Assigned, SystemEvent::Reassigned => "{$name} is handling this conversation",
            SystemEvent::Released => "{$name} released this conversation",
            default => '',
        };
    }
}
