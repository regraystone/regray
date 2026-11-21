<?php

namespace App\Services\Notification;

use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Events\InquiryStatusChanged;
use App\Models\Inquiry;
use App\Models\InquiryHandler;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\InquiryReceivedNotification;
use App\Notifications\InquiryStatusUpdatedNotification;
use App\Services\InquiryStatusCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

/**
 * Inquiry notifications. The owner and the admin are told about every one, in
 * the words staff are.
 *
 * The staff assigned on an inquiry are the marketing user who holds its lead
 * (who claimed the client) and its handlers (inquiry_handlers: sales,
 * technical, accounting, logistics), as they stand when the notification is
 * sent: someone who has lost the assignment is no longer told. An inquiry is
 * claimed once it has either.
 *
 * - The client sends, updates or cancels one: the client is sent a
 *   confirmation; its assigned staff are told, or, while it is unclaimed, every
 *   marketing and sales user. Never for a walk-in's inquiry, which only its
 *   owner, its handlers and the marketing member who opened it hear about
 *   (with the owner and the admin), assigned or not. A new one is also confirmed to the client by
 *   email (InquiryReceivedNotification), whatever their email box says.
 * - Staff open one: the staff member is told, and its client too when it is
 *   for a client account (not for a walk-in), and a walk-in's owner when
 *   someone else opened it for them.
 * - A status moves: its assigned staff are told the role status that moved,
 *   and the client the status they see (InquiryStatusCalculator) when it
 *   changed, or the payment status, which they are told about every time.
 *
 * The client is also emailed whatever they are told about their inquiry's
 * status, and their own cancel (InquiryStatusUpdatedNotification), whatever
 * their email box says: a verified address is all it needs.
 *
 * Never whoever made the change, except for the confirmations above.
 */
class InquiryNotificationService
{
    public function __construct(
        public readonly NotificationService $notifications,
    ) {}

    public function submitted(Inquiry $inquiry, ?int $createdBy): void
    {
        $client = $this->client($inquiry);
        $clientName = $this->clientName($inquiry, $client);
        $reference = $inquiry->reference;

        if ($createdBy === null) {
            $this->send($client === null ? [] : [$client], $inquiry, "You have successfully submitted inquiry {$reference}.");
            $this->send($this->concerned($inquiry), $inquiry, "{$clientName} has submitted inquiry {$reference}.");

            $this->email($client, new InquiryReceivedNotification($inquiry));

            return;
        }

        $creator = User::query()->find($createdBy);

        if ($creator !== null) {
            $this->send([$creator], $inquiry, "You have successfully created inquiry {$reference} for {$clientName}.");
        }

        if ($client !== null) {
            $this->send([$client], $inquiry, "Inquiry {$reference} has been created for you.");
        }

        $owner = $inquiry->owner_user_id === null || $inquiry->owner_user_id === $createdBy ? null : User::query()->find($inquiry->owner_user_id);

        if ($owner !== null) {
            $this->send([$owner], $inquiry, ($creator->name ?? 'Regray')." has created inquiry {$reference} for {$clientName} and made you its owner.");
        }

        $this->send($this->ownerAndAdmin()->reject(fn (User $user): bool => $user->id === $createdBy), $inquiry, ($creator->name ?? 'Regray')." has created inquiry {$reference} for {$clientName}.");
    }

    /** @param  'updated'|'cancelled'  $change */
    public function changedByClient(Inquiry $inquiry, string $change): void
    {
        $client = $this->client($inquiry);

        $this->send($client === null ? [] : [$client], $inquiry, "You have successfully {$change} inquiry {$inquiry->reference}.");
        $this->send($this->concerned($inquiry)->reject(fn (User $user): bool => $user->id === $client?->id), $inquiry, $this->clientName($inquiry, $client)." has {$change} inquiry {$inquiry->reference}.");

        if ($change === 'cancelled') {
            $this->email($client, new InquiryStatusUpdatedNotification($inquiry, InquiryStatus::Cancelled, cancelledByClient: true));
        }
    }

    public function statusChanged(InquiryStatusChanged $event): void
    {
        $inquiry = $event->inquiry;
        $client = $this->client($inquiry);

        // Opening an inquiry is InquirySubmitted's, and the client's cancel InquiryChangedByClient's.
        if ($event->opening || ($client !== null && $event->changedBy === $client->id)) {
            return;
        }

        $raw = $this->rawStatus($event);

        if ($raw === null) {
            return;
        }

        $staff = $this->assigned($inquiry)->merge($this->ownerAndAdmin())
            ->reject(fn (User $user): bool => $user->id === $event->changedBy || $user->isClient());

        $actor = $event->changedBy === null ? null : User::query()->find($event->changedBy);
        $this->send($staff, $inquiry, $actor === null
            ? "Inquiry {$inquiry->reference} has moved to {$raw}."
            : "{$actor->name} has moved inquiry {$inquiry->reference} to {$raw}.");

        $clientStatus = $client === null ? null : $this->clientStatus($event);

        if ($client !== null && $clientStatus !== null) {
            $words = strtolower($clientStatus instanceof PaymentStatus ? $clientStatus->clientLabel() : $clientStatus->label());
            $this->send([$client], $inquiry, "Your inquiry {$inquiry->reference} is now {$words}.");
            $this->email($client, new InquiryStatusUpdatedNotification($inquiry, $clientStatus));
        }
    }

    /** "on hold (logistics)", "won (marketing)": the role status that moved, and whose it is, as it reads in a sentence. */
    private function rawStatus(InquiryStatusChanged $event): ?string
    {
        if ($event->decision !== null) {
            return strtolower($event->decision->label()).' (marketing)';
        }

        if ($event->track === null || $event->to === null) {
            return null;
        }

        return strtolower($event->to->label())." ({$event->track->team()})";
    }

    /**
     * What the client is told the inquiry is at now: its payment status when
     * that moved, otherwise the status they see, when this move changed it.
     */
    private function clientStatus(InquiryStatusChanged $event): InquiryStatus|PaymentStatus|null
    {
        if ($event->to instanceof PaymentStatus) {
            return $event->to;
        }

        $inquiry = $event->inquiry;
        $after = $inquiry->status;
        $before = InquiryStatusCalculator::calculate(
            $event->decision !== null ? null : $inquiry->marketing_status,
            $event->track === InquiryStatusTrack::Sales ? $this->sales($event->from) : $inquiry->sales_status,
            $event->track === InquiryStatusTrack::Process ? $this->process($event->from) : $inquiry->process_status,
            $inquiry->hasJobOrder(),
            $inquiry->payment_status,
        );

        return $before === $after ? null : $after;
    }

    private function sales(SalesStatus|PaymentStatus|ProcessStatus|null $status): ?SalesStatus
    {
        return $status instanceof SalesStatus ? $status : null;
    }

    private function process(SalesStatus|PaymentStatus|ProcessStatus|null $status): ?ProcessStatus
    {
        return $status instanceof ProcessStatus ? $status : null;
    }

    /**
     * Who hears about the client's own changes, the owner and the admin
     * included: its assigned staff, or every marketing and sales user while
     * it is unclaimed. A walk-in's inquiry is never anyone's but its own
     * people's: its owner, handlers and the marketing member who opened it,
     * even while none of them is assigned.
     *
     * @return Collection<int, User>
     */
    private function concerned(Inquiry $inquiry): Collection
    {
        $assigned = $this->assigned($inquiry);
        $staff = $assigned->isNotEmpty() || $inquiry->isWalkIn()
            ? $assigned
            : User::role([Role::Marketing->value, Role::Sales->value])->get();

        return $staff->merge($this->ownerAndAdmin())->reject(fn (User $user): bool => $user->isClient())->values();
    }

    /**
     * Its handlers, and the marketing user holding its lead; for a walk-in's,
     * its owner and the staff member who opened it instead of a lead holder.
     *
     * @return Collection<int, User>
     */
    private function assigned(Inquiry $inquiry): Collection
    {
        $leadHolder = $inquiry->lead_id === null ? null : Lead::query()->whereKey($inquiry->lead_id)->value('marketing_user_id');
        $walkInPeople = $inquiry->isWalkIn() ? array_values(array_filter([$inquiry->owner_user_id, $inquiry->created_by])) : [];

        return User::query()
            ->whereIn('id', InquiryHandler::query()->where('inquiry_id', $inquiry->id)->select('user_id'))
            ->when($leadHolder !== null, fn ($query) => $query->orWhere('id', $leadHolder))
            ->when($walkInPeople !== [], fn ($query) => $query->orWhereIn('id', $walkInPeople))
            ->get();
    }

    /** @return Collection<int, User> */
    private function ownerAndAdmin(): Collection
    {
        return User::role([Role::Owner->value, Role::Admin->value])->get();
    }

    /** The client account the inquiry is for: null for a walk-in. */
    private function client(Inquiry $inquiry): ?User
    {
        return $inquiry->client_id === null ? null : User::query()->find($inquiry->client_id);
    }

    private function clientName(Inquiry $inquiry, ?User $client): string
    {
        return $client->name ?? $inquiry->clientRecord->full_name ?? 'a client';
    }

    /**
     * Email the client about their own inquiry: a verified address is all it
     * needs, since these are not what the email box covers.
     */
    private function email(?User $client, Notification $notification): void
    {
        if ($client !== null && $client->email_verified_at !== null) {
            $client->notify($notification);
        }
    }

    /** @param  iterable<User>  $recipients */
    private function send(iterable $recipients, Inquiry $inquiry, string $body): void
    {
        $this->notifications->send($recipients, NotificationType::Inquiry, $inquiry->id, $body);
    }
}
