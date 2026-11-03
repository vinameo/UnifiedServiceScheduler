<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS customers_full_name_trgm_idx ON customers USING gin (lower(full_name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS customers_phone_prefix_idx ON customers (phone text_pattern_ops)');
        DB::statement("CREATE INDEX IF NOT EXISTS users_email_prefix_idx ON users (email text_pattern_ops) WHERE role = 'CUSTOMER'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_prefix_idx');
        DB::statement('DROP INDEX IF EXISTS customers_phone_prefix_idx');
        DB::statement('DROP INDEX IF EXISTS customers_full_name_trgm_idx');
    }
};
