<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Services\Notification\ScheduleNotificationService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class RemindSchedulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedules:remind';

    /**
     * The console command description.
     */
    protected $description = 'Remind, once each, a sales member of an appointment still waiting for them to confirm or decline, and marketing of one still with no sales member.';

    public function __construct(
        public readonly ScheduleRepositoryInterface $scheduleRepository,
        public readonly ScheduleNotificationService $notifications,
    ) {
        parent::__construct();
    }

    /**
     * One at a time, each on its locked row, so one confirmed, declined or
     * assigned while this runs is not reminded from a stale read. Not a change
     * to the appointment: no revision, audit entry or chat card.
     */
    public function handle(): int
    {
        $now = Date::now();

        $sales = $this->remind(
            $this->scheduleRepository->dueSalesReminderIds($now),
            fn (Schedule $schedule): bool => $schedule->isDueSalesReminder($now),
            'sales_reminded_at',
            $now,
            fn (Schedule $schedule) => $this->notifications->remindSales($schedule),
        );

        $marketing = $this->remind(
            $this->scheduleRepository->dueMarketingReminderIds($now),
            fn (Schedule $schedule): bool => $schedule->isDueMarketingReminder($now),
            'marketing_reminded_at',
            $now,
            fn (Schedule $schedule) => $this->notifications->remindMarketing($schedule),
        );

        $this->info("Reminded sales of {$sales} and marketing of {$marketing} appointments.");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $ids
     * @param  callable(Schedule): bool  $isDue
     * @param  callable(Schedule): void  $send
     */
    private function remind(array $ids, callable $isDue, string $column, CarbonInterface $now, callable $send): int
    {
        $reminded = 0;

        foreach ($ids as $id) {
            $schedule = DB::transaction(function () use ($id, $isDue, $column, $now): ?Schedule {
                $locked = $this->scheduleRepository->findForUpdate($id);

                if (! $isDue($locked)) {
                    return null;
                }

                $this->scheduleRepository->update($locked, [$column => $now]);

                return $locked;
            });

            if ($schedule !== null) {
                $send($schedule);
                $reminded++;
            }
        }

        return $reminded;
    }
}
