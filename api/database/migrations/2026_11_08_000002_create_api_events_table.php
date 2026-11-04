<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_events', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('occurred_at');
            $table->string('correlation_id', 128)->index();
            $table->uuid('dealership_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('appointment_id')->nullable();
            $table->string('method', 8);
            $table->string('route', 160);
            $table->smallInteger('status')->nullable();
            $table->string('code', 48)->nullable();
            $table->string('outcome', 24)->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('idempotent_replay')->default(false);
            $table->smallInteger('allocation_retries')->default(0);
            $table->jsonb('detail')->default('{}');

            $table->index('occurred_at');
            $table->index(['dealership_id', 'occurred_at']);
        });

        DB::statement("CREATE INDEX api_events_booking_occurred_at_idx ON api_events (route, occurred_at) WHERE route = 'POST api/v1/appointments'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_events');
    }
};
