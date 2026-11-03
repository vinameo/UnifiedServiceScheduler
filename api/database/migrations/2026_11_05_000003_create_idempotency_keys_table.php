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
            CREATE TABLE IF NOT EXISTS idempotency_keys (
                id UUID PRIMARY KEY,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                key UUID NOT NULL,
                method VARCHAR(8) NOT NULL,
                path VARCHAR(255) NOT NULL,
                request_hash CHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL,
                locked_until TIMESTAMPTZ NOT NULL,
                response_status SMALLINT NULL,
                response_headers JSONB NULL,
                response_body TEXT NULL,
                created_at TIMESTAMPTZ NOT NULL,
                expires_at TIMESTAMPTZ NOT NULL,
                CONSTRAINT idempotency_keys_user_key_unique UNIQUE (user_id, key),
                CONSTRAINT idempotency_keys_status_valid CHECK (status IN ('IN_PROGRESS', 'COMPLETED')),
                CONSTRAINT idempotency_keys_completed_requires_response_status CHECK (status <> 'COMPLETED' OR response_status IS NOT NULL)
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS idempotency_keys_expires_at_idx ON idempotency_keys (expires_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS idempotency_keys');
    }
};
