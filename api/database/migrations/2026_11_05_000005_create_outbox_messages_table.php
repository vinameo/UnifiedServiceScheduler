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
            CREATE TABLE IF NOT EXISTS outbox_messages (
                id UUID PRIMARY KEY,
                event_id UUID NOT NULL,
                dealership_id UUID NOT NULL REFERENCES dealerships(id) ON DELETE RESTRICT,
                appointment_id UUID NULL REFERENCES appointments(id) ON DELETE RESTRICT,
                event_type VARCHAR(40) NOT NULL,
                channel VARCHAR(8) NOT NULL,
                payload JSONB NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
                attempts SMALLINT NOT NULL DEFAULT 0,
                next_attempt_at TIMESTAMPTZ NOT NULL,
                last_error VARCHAR(500) NULL,
                sent_at TIMESTAMPTZ NULL,
                dedupe_key VARCHAR(120) NULL UNIQUE,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                CONSTRAINT outbox_messages_event_channel_unique UNIQUE (event_id, channel),
                CONSTRAINT outbox_messages_event_type_valid CHECK (event_type IN (
                    'AppointmentConfirmed', 'AppointmentCancelled', 'AppointmentRescheduled',
                    'AppointmentReminder', 'AppointmentReassigned'
                )),
                CONSTRAINT outbox_messages_channel_valid CHECK (channel IN ('SMS', 'EMAIL', 'DMS')),
                CONSTRAINT outbox_messages_status_valid CHECK (status IN ('PENDING', 'SENT', 'FAILED', 'SKIPPED'))
            )
        SQL);

        DB::statement("CREATE INDEX IF NOT EXISTS outbox_messages_pending_idx ON outbox_messages (next_attempt_at) WHERE status = 'PENDING'");
        DB::statement('CREATE INDEX IF NOT EXISTS outbox_messages_dealership_status_created_idx ON outbox_messages (dealership_id, status, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS outbox_messages_appointment_id_idx ON outbox_messages (appointment_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS outbox_messages');
    }
};
