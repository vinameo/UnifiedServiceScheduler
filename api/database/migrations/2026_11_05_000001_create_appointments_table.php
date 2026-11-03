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
        DB::statement('CREATE SEQUENCE IF NOT EXISTS appointment_reference_seq START 1');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS appointments (
                id UUID PRIMARY KEY,
                reference VARCHAR(32) NOT NULL UNIQUE,
                dealership_id UUID NOT NULL REFERENCES dealerships(id) ON DELETE RESTRICT,
                customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
                vehicle_id UUID NULL REFERENCES vehicles(id) ON DELETE RESTRICT,
                service_type_id UUID NOT NULL REFERENCES service_types(id) ON DELETE RESTRICT,
                technician_id UUID NOT NULL REFERENCES technicians(id) ON DELETE RESTRICT,
                bay_id UUID NOT NULL REFERENCES bays(id) ON DELETE RESTRICT,
                status VARCHAR(16) NOT NULL,
                start_at TIMESTAMPTZ NOT NULL,
                end_at TIMESTAMPTZ NOT NULL,
                expires_at TIMESTAMPTZ NULL,
                version INTEGER NOT NULL DEFAULT 1,
                source VARCHAR(16) NOT NULL,
                reminder_opt_in BOOLEAN NOT NULL DEFAULT TRUE,
                created_by_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                cancel_reason VARCHAR(32) NULL,
                cancel_note VARCHAR(500) NULL,
                cancelled_at TIMESTAMPTZ NULL,
                cancelled_by_user_id BIGINT NULL REFERENCES users(id) ON DELETE RESTRICT,
                contact_required_reason VARCHAR(32) NULL,
                checked_in_at TIMESTAMPTZ NULL,
                started_at TIMESTAMPTZ NULL,
                completed_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL,
                updated_at TIMESTAMPTZ NOT NULL,
                CONSTRAINT appointments_end_after_start CHECK (end_at > start_at),
                CONSTRAINT appointments_held_requires_expires_at CHECK (status <> 'HELD' OR expires_at IS NOT NULL),
                CONSTRAINT appointments_version_positive CHECK (version >= 1),
                CONSTRAINT appointments_status_valid CHECK (status IN ('HELD', 'CONFIRMED', 'CHECKED_IN', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW', 'EXPIRED')),
                CONSTRAINT appointments_source_valid CHECK (source IN ('STAFF', 'CUSTOMER')),
                CONSTRAINT appointments_contact_required_reason_valid CHECK (contact_required_reason IS NULL OR contact_required_reason = 'OVERRUN'),
                CONSTRAINT appointments_confirmed_requires_vehicle CHECK (vehicle_id IS NOT NULL OR status IN ('HELD', 'EXPIRED', 'CANCELLED'))
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS appointments_dealership_start_idx ON appointments (dealership_id, start_at, id)');
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_customer_start_idx ON appointments (customer_id, start_at, id)');
        DB::statement("CREATE INDEX IF NOT EXISTS appointments_held_expiry_idx ON appointments (expires_at) WHERE status = 'HELD'");
        DB::statement('CREATE INDEX IF NOT EXISTS appointments_contact_required_idx ON appointments (dealership_id) WHERE contact_required_reason IS NOT NULL');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION appointments_version_guard() RETURNS trigger AS $$
            BEGIN
              IF NEW.version IS DISTINCT FROM OLD.version + 1 THEN
                RAISE EXCEPTION 'appointments.version must increase by exactly 1 (id %, % -> %)', OLD.id, OLD.version, NEW.version
                  USING ERRCODE = 'P0001';
              END IF;
              RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('DROP TRIGGER IF EXISTS appointments_version_guard ON appointments');
        DB::statement(<<<'SQL'
            CREATE TRIGGER appointments_version_guard
              BEFORE UPDATE ON appointments
              FOR EACH ROW
              EXECUTE FUNCTION appointments_version_guard();
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS appointments_version_guard ON appointments');
        DB::statement('DROP FUNCTION IF EXISTS appointments_version_guard()');
        DB::statement('DROP TABLE IF EXISTS appointments');
        DB::statement('DROP SEQUENCE IF EXISTS appointment_reference_seq');
    }
};
