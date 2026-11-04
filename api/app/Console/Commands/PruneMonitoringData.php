<?php

namespace App\Console\Commands;

use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PruneMonitoringData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitoring:prune {--days=7}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune api_events older than the retention period';

    /**
     * Execute the console command.
     */
    public function handle(ApiEventRepositoryInterface $repository): int
    {
        $rawDays = $this->option('days');
        if (! is_numeric($rawDays) || (int) $rawDays <= 0) {
            $this->error('The --days option must be a positive integer.');

            return self::FAILURE;
        }

        $days = (int) $rawDays;
        $cutoff = CarbonImmutable::now()->subDays($days);
        $deleted = $repository->pruneOlderThan($cutoff);

        $this->info("Pruned {$deleted} api_events older than {$cutoff->toIso8601String()}.");

        return self::SUCCESS;
    }
}
