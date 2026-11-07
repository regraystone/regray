<?php

namespace App\Console\Commands;

use App\Actions\Message\SendMessageRemindersAction;
use Illuminate\Console\Command;

class SendMessageRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'messages:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Email people about messages that have waited unread, and the support pool about unclaimed clients.';

    public function __construct(
        public readonly SendMessageRemindersAction $action
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = $this->action->execute();

        $this->info("Reminded {$sent['members']} members, {$sent['clients']} clients and {$sent['pool']} support staff.");

        return self::SUCCESS;
    }
}
