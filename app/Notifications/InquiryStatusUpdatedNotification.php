<?php

namespace App\Notifications;

use App\Enums\InquiryStatus;
use App\Enums\PaymentStatus;
use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email that tells a client their inquiry has moved: the status they see
 * now (InquiryStatusCalculator, the badge's words) or its payment status, and
 * what that means for them. Sent alongside the bell's "Your inquiry {ref} is
 * now {status}." (InquiryNotificationService), and when the client cancels
 * it themselves (`$cancelledByClient`).
 *
 * About the client's own inquiry, so it is sent whether or not they want
 * emails from Regray (User::wantsMarketingEmails()).
 */
class InquiryStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Inquiry $inquiry,
        public readonly InquiryStatus|PaymentStatus $status,
        public readonly bool $cancelledByClient = false,
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
        $reference = $this->inquiry->reference;

        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hi '.($notifiable->name ?? 'there').',');

        foreach ($this->explanation() as $line) {
            $message->line($line);
        }

        $message->line("Reference: {$reference}")
            ->line("Project: {$this->inquiry->name}");

        if ($this->status === InquiryStatus::Cancelled) {
            return $message
                ->line('It stays on your Quote page in Regray, should you want to look back at it.')
                ->action('Send a new inquiry', route('quote.inquire'))
                ->line('Whenever you are ready for another project, we would be glad to help.');
        }

        return $message
            ->action('View your inquiry', route('quote.show', $reference))
            ->line("Questions? Message our team from Messages in Regray, and mention {$reference}.");
    }

    private function subject(): string
    {
        $reference = $this->inquiry->reference;

        return match (true) {
            $this->cancelledByClient => "You cancelled inquiry {$reference}",
            $this->status === InquiryStatus::Cancelled => "Your inquiry {$reference} has been cancelled",
            $this->status === InquiryStatus::Closed => "Your inquiry {$reference} is complete",
            default => "Your inquiry {$reference} is now {$this->statusWords()}",
        };
    }

    /** The status as the bell says it: "on hold", "partially paid". */
    private function statusWords(): string
    {
        return strtolower($this->status instanceof PaymentStatus ? $this->status->clientLabel() : $this->status->label());
    }

    /**
     * What the status means for the client, and what happens next.
     *
     * @return list<string>
     */
    private function explanation(): array
    {
        if ($this->status instanceof PaymentStatus) {
            return [match ($this->status) {
                PaymentStatus::Awaiting => 'Thank you for approving your quotation. Your inquiry is now awaiting payment: open it to see the amount due and how to pay.',
                PaymentStatus::Partial => 'We have received your payment, thank you. Part of the balance is still due: open your inquiry to see what remains.',
                PaymentStatus::FullyPaid => 'Your inquiry is now fully paid, thank you. Our team will keep you updated as your project moves along.',
                PaymentStatus::PartiallyRefunded => 'Part of your payment has been refunded. Open your inquiry to see the details.',
                PaymentStatus::Refunded => 'Your payment has been refunded in full.',
            }];
        }

        if ($this->status === InquiryStatus::Cancelled) {
            return [
                $this->cancelledByClient
                    ? 'You have cancelled your inquiry, as you asked. Our team will not work on it any further.'
                    : 'Your inquiry has been cancelled, and our team will not work on it any further.',
                'If you have made a payment for it, our team will contact you about your refund.',
            ];
        }

        return [match ($this->status) {
            InquiryStatus::Negotiation => 'Your inquiry is under review: our team is going through it and will discuss the details and the price with you.',
            InquiryStatus::SiteVisited => 'Your inquiry is under review: we have visited your site, and our team is preparing your quotation.',
            InquiryStatus::Quoted => 'Your quotation is ready. Open your inquiry to review it, and approve it when you are happy with it.',
            InquiryStatus::Revision => 'Your quotation is being revised. We will let you know as soon as the new one is ready.',
            InquiryStatus::OnHold => 'Your inquiry is on hold for now. Our team may need more information or a decision from you: check your messages in Regray.',
            InquiryStatus::Delayed => 'Your order is taking longer than planned. Our team will update you on the new timing; message us if you have any questions.',
            InquiryStatus::OrderReceived => 'Your order is being processed: we have received it and started work on it.',
            InquiryStatus::Measure => 'Your order is being processed: we are taking the measurements for your project.',
            InquiryStatus::Sourcing => 'Your order is being processed: we are sourcing the materials for your project.',
            InquiryStatus::Production => 'Your order is being processed: your stone pieces are being made.',
            InquiryStatus::QualityCheck => 'Your order is being processed: your pieces are going through our quality check.',
            InquiryStatus::Ready => 'Your order is ready. Our team will contact you to arrange the delivery or pickup.',
            InquiryStatus::Dispatched => 'Your order is on its way to you.',
            InquiryStatus::Installation => 'Our team is installing your project.',
            InquiryStatus::Closed => 'Your project is complete. Thank you for choosing Regray; we hope you enjoy it.',
            InquiryStatus::ForRefund => 'Your refund is being processed. Our team will contact you about it.',
            InquiryStatus::Pending, InquiryStatus::Won => "Your inquiry is now {$this->statusWords()}.",
        }];
    }
}
