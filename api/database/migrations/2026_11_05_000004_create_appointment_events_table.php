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
        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS appointment_events (
                id UUID PRIMARY KEY,
                appointment_id UUID NOT NULL REFERENCES appointments(id) ON DELETE RESTRICT,
                dealership_id UUID NOT NULL REFERENCES dealerships(id) ON DELETE RESTRICT,
                type VARCHAR(32) NOT NULL,
                from_status VARCHAR(16) NULL,
                to_status VARCHAR(16) NULL,
                version INTEGER NOT NULL,
                actor_user_id BIGINT NULL REFERENCES users(id) ON DELETE RESTRICT,
                actor_role VARCHAR(32) NULL,
                data JSONB NOT NULL DEFAULT '{}',
                occurred_at TIMESTAMPTZ NOT NULL,
                CONSTRAINT appointment_events_type_valid CHECK (type IN (
                    'CREATED', 'HELD', 'CONFIRMED', 'CANCELLED', 'RESCHEDULED', 'EXPIRED',
                    'CHECKED_IN', 'STARTED', 'COMPLETED', 'NO_SHOW', 'EXTENDED', 'REASSIGNED', 'CONTACT_REQUIRED'
                ))
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS appointment_events_appointment_occurred_idx ON appointment_events (appointment_id, occurred_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS appointment_events');
    }
};
