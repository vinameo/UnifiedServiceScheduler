<?php

namespace App\Console\Commands;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:expire
                            {--now= : ISO timestamp for app clock (testing only)}
                            {--start-at= : Unix timestamp with microsecond precision to wait for (testing only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire held appointments whose hold window has elapsed';

    /**
     * Execute the console command.
     */
    public function handle(
        AppointmentRepositoryInterface $appointmentRepository,
        AppointmentTransitionInterface $appointmentTransition,
    ): int {
        $startAtOption = $this->option('start-at');
        if ($startAtOption !== null && is_numeric($startAtOption) && app()->environment('testing')) {
            $targetTime = (float) $startAtOption;
            while (microtime(true) < $targetTime) {
                usleep(100);
            }
        }

        $now = CarbonImmutable::now();
        $nowOption = $this->option('now');

        if ($nowOption !== null) {
            if (app()->environment('testing')) {
                $now = CarbonImmutable::parse((string) $nowOption);
            } else {
                $this->warn('--now option is only permitted in the testing environment; using current time.');
            }
        }

        $expiredHolds = $appointmentRepository->findExpiredHolds($now, limit: 500);

        $expiredCount = 0;
        foreach ($expiredHolds as $hold) {
            try {
                $appointmentTransition->transition(
                    id: $hold->id,
                    expectedVersion: $hold->version,
                    to: AppointmentStatus::EXPIRED,
                    changes: [],
                    outbox: null,
                    actor: null,
                    now: $now,
                );
                $expiredCount++;
            } catch (AppointmentModified|InvalidStatusTransition|AppointmentNotFound) {
                // Hold was concurrently modified, confirmed, or cancelled: skip gracefully
                continue;
            }
        }

        $this->info("Expired {$expiredCount} hold(s).");
        Log::info('holds:expire completed', [
            'count' => $expiredCount,
            'now' => $now->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
