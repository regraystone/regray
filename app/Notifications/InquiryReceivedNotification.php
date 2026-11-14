<?php

namespace App\Notifications;

use App\Enums\DeliveryMethod;
use App\Models\Inquiry;
use App\Models\InquiryStone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that confirms a client's own inquiry has been received
 * (InquiryNotificationService::submitted()): its reference, what was asked
 * for, and where to follow it.
 *
 * A receipt for something the client did, like a password reset, so it is
 * sent whether or not they want emails from Regray (User::wantsMarketingEmails()).
 * Not sent for an inquiry staff open for a client.
 */
class InquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Inquiry $inquiry,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry->loadMissing([
            'service:id,name',
            'schedule:id,inquiry_id,arrival_at',
            'stones' => fn ($query) => $query->orderBy('sort_order')->with('stone:id,name'),
        ]);
        $reference = $inquiry->reference;

        $message = (new MailMessage)
            ->subject("We received your inquiry {$reference}")
            ->greeting('Hi '.($notifiable->name ?? 'there').',')
            ->line('Thank you for your inquiry. We have received it, and our team will review it shortly.')
            ->line("Reference: {$reference}")
            ->line("Project: {$inquiry->name}");

        foreach ($this->details($inquiry) as $line) {
            $message->line($line);
        }

        return $message
            ->line('You can follow its progress on your Quote page in Regray: its status, your visit, and the quotation once it is ready. We will notify you in Regray whenever it moves, and you can message our team from Messages.')
            ->action('View your inquiry', route('quote.show', $reference))
            ->line("Please mention {$reference} whenever you contact us about this inquiry.");
    }

    /**
     * The service, stones, delivery and date, each line only when the inquiry has it.
     *
     * @return list<string>
     */
    private function details(Inquiry $inquiry): array
    {
        $lines = [];

        if ($inquiry->service !== null) {
            $lines[] = "Service: {$inquiry->service->name}";
        }

        $stones = $inquiry->stones
            ->map(fn (InquiryStone $row): ?string => $row->stone?->name)
            ->filter()
            ->unique()
            ->implode(', ');

        if ($stones !== '') {
            $lines[] = "Stones: {$stones}";
        } elseif ($inquiry->stone_advice_requested) {
            $lines[] = 'Stones: you asked us to recommend one';
        }

        $lines[] = $inquiry->delivery_method === DeliveryMethod::Delivery
            ? "Delivery: to {$inquiry->location}"
            : 'Delivery: you will pick it up';

        if ($inquiry->schedule !== null) {
            $lines[] = 'Needed by: '.$inquiry->schedule->arrival_at->copy()->setTimezone((string) config('schedule.timezone'))->format('M j, Y');
        }

        return $lines;
    }
}
