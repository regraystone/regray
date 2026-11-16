<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Services\ScheduleChangeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

class ExpireSchedulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedules:expire';

    /**
     * The console command description.
     */
    protected $description = 'Expire the appointments still pending or awaiting sales when their time has passed, and tell each client.';

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
        public readonly ScheduleChangeService $scheduleChangeService,
    ) {
        parent::__construct();
    }

    /**
     * One at a time, each on its locked row, so one approved or moved while this
     * runs is left alone rather than expired from a stale read.
     */
    public function handle(): int
    {
        $expired = 0;

        foreach ($this->scheduleRepository->pendingPastIds(Date::now()) as $id) {
            if ($this->scheduleChangeService->expire($id)) {
                $expired++;
            }
        }

        $this->info("Expired {$expired} appointments.");

        return self::SUCCESS;
    }
}
