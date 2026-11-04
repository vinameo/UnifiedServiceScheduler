<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Services\OutboxRelayInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RelayOutbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outbox:relay
                            {--batch=50 : Maximum number of messages to process}
                            {--now= : ISO timestamp for app clock (testing only)}
                            {--start-at= : Unix timestamp with microsecond precision to wait for (testing only)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Relay pending outbox notification messages through their channels';

    /**
     * Execute the console command.
     */
    public function handle(OutboxRelayInterface $relay): int
    {
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

        $batch = (int) $this->option('batch');
        if ($batch <= 0) {
            $batch = 50;
        }

        $result = $relay->runOnce($now, $batch);
        $this->info("Relayed outbox messages: claimed={$result->claimed}, sent={$result->sent}, failed={$result->failed}, skipped={$result->skipped}.");

        return self::SUCCESS;
    }
}
