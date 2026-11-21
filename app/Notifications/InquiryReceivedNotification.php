<?php

namespace App\Notifications;

use App\Enums\DeliveryMethod;
use App\Models\Inquiry;
use App\Models\InquiryDetailAnswer;
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
            'detailAnswers.detailField',
            'detailAnswers.option',
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
     * The service, stones, the project's answers, delivery and date, each line
     * only when the inquiry has it. A stone carries the slab size and
     * thickness asked of it ("Cosmic Black (160 x 320 cm, 20 mm)"), so two rows
     * of one stone in different sizes both show; an answer is worded as the
     * inquiry page words it ("Project area (sqm): 3.75", "Fascia: Yes").
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
            ->map(function (InquiryStone $row): ?string {
                $measurements = array_filter([$row->slabSizeLabel(), $row->thicknessLabel()]);

                return $row->stone?->name === null || $measurements === []
                    ? $row->stone?->name
                    : $row->stone->name.' ('.implode(', ', $measurements).')';
            })
            ->filter()
            ->unique()
            ->implode(', ');

        if ($stones !== '') {
            $lines[] = "Stones: {$stones}";
        } elseif ($inquiry->stone_advice_requested) {
            $lines[] = 'Stones: you asked us to recommend one';
        }

        $firstStone = $inquiry->stones->first()?->stone?->name;

        // In the catalogue's order, which is the order the questions are asked in.
        foreach ($inquiry->detailAnswers->sortBy(fn (InquiryDetailAnswer $answer): int => $answer->detailField->sort_order) as $answer) {
            $lines[] = "{$answer->detailField->name}: {$answer->displayValue($firstStone)}";
        }

        $lines[] = $inquiry->delivery_method === DeliveryMethod::Pickup
            ? 'Delivery: you will pick it up'
            : ($inquiry->delivery_method === DeliveryMethod::Delivery ? 'Delivery' : 'Installation').': '.($inquiry->address()?->line() ?? '');

        if ($inquiry->schedule !== null) {
            $lines[] = 'Needed by: '.$inquiry->schedule->arrival_at->copy()->setTimezone((string) config('schedule.timezone'))->format('M j, Y');
        }

        return $lines;
    }
}
