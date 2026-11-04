<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /** @var list<object{customer_id: string, count: int|string}> $conflicts */
        $conflicts = DB::select(
            "SELECT customer_id, count(*) AS count FROM appointments WHERE status = 'HELD' GROUP BY 1 HAVING count(*) > 1"
        );

        if (! empty($conflicts)) {
            $ids = implode(', ', array_map(static fn (object $r): string => (string) $r->customer_id, $conflicts));

            throw new RuntimeException(
                "Cannot create unique index appointments_one_hold_per_customer: conflicting customer ID(s) with multiple active holds found: [{$ids}]. Expire holds first (run holds:expire)."
            );
        }

        DB::statement("CREATE UNIQUE INDEX appointments_one_hold_per_customer ON appointments (customer_id) WHERE status = 'HELD'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_one_hold_per_customer');
    }
};
