<?php

namespace App\Console\Commands;

use App\Services\Notification\MessageNotificationService;
use Illuminate\Console\Command;

/**
 * Sends the message notifications whose timers are due and have not gone:
 * the ones a queued DeliverMessageNotification missed (lost, or run early by a
 * queue that ignores delays). Runs every minute.
 */
class DeliverDueNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:deliver-due';

    /**
     * The console command description.
     */
    protected $description = 'Send message notifications and emails whose delay has passed.';

    public function __construct(
        public readonly MessageNotificationService $notifications,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = $this->notifications->deliverDue();

        $this->info("Sent {$sent} message notifications.");

        return self::SUCCESS;
    }
}
