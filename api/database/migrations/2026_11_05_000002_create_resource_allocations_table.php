<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS resource_allocations (
                id UUID PRIMARY KEY,
                appointment_id UUID NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
                dealership_id UUID NOT NULL REFERENCES dealerships(id) ON DELETE RESTRICT,
                resource_type VARCHAR(16) NOT NULL,
                resource_id UUID NOT NULL,
                period TSTZRANGE NOT NULL,
                active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL,
                deactivated_at TIMESTAMPTZ NULL,
                CONSTRAINT resource_allocations_resource_type_valid CHECK (resource_type IN ('TECHNICIAN', 'BAY', 'VEHICLE')),
                CONSTRAINT resource_allocations_period_valid CHECK (NOT isempty(period) AND lower_inc(period) AND NOT upper_inc(period) AND NOT lower_inf(period) AND NOT upper_inf(period)),
                CONSTRAINT resource_allocations_deactivated_when_inactive CHECK (active OR deactivated_at IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE resource_allocations
            ADD CONSTRAINT resource_allocations_no_overlap
            EXCLUDE USING gist (resource_type WITH =, resource_id WITH =, period WITH &&)
            WHERE (active)
        SQL);

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS resource_allocations_one_active_per_type ON resource_allocations (appointment_id, resource_type) WHERE active');
        DB::statement('CREATE INDEX IF NOT EXISTS resource_allocations_dealership_period_idx ON resource_allocations USING gist (dealership_id, period) WHERE active');
        DB::statement('CREATE INDEX IF NOT EXISTS resource_allocations_appointment_id_idx ON resource_allocations (appointment_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS resource_allocations');
    }
};
