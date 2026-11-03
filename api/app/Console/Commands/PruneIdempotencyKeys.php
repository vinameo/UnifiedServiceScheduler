<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneIdempotencyKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idempotency:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired idempotency keys';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $deleted = DB::table('idempotency_keys')
            ->where('expires_at', '<=', $now)
            ->delete();

        $this->info("Pruned {$deleted} expired idempotency keys.");

        return self::SUCCESS;
    }
}
