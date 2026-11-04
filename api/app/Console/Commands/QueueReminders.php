<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Services\ReminderQueueInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class QueueReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:queue-reminders
                            {--now= : ISO timestamp for app clock (testing only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue appointment reminder SMS messages for upcoming confirmed bookings';

    /**
     * Execute the console command.
     */
    public function handle(ReminderQueueInterface $reminderQueue): int
    {
        $now = CarbonImmutable::now();
        $nowOption = $this->option('now');

        if ($nowOption !== null) {
            if (app()->environment('testing')) {
                $now = CarbonImmutable::parse((string) $nowOption);
            } else {
                $this->warn('--now option is only permitted in the testing environment; using current time.');
            }
        }

        $queued = $reminderQueue->queue($now);
        $this->info("Queued {$queued} reminder message(s).");

        return self::SUCCESS;
    }
}
