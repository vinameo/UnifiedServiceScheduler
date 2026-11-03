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
        Schema::create('dealership_opening_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dealership_id')
                ->constrained('dealerships')
                ->cascadeOnDelete();
            $table->smallInteger('weekday');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestampsTz();

            $table->unique(['dealership_id', 'weekday'], 'dealership_opening_hours_dealership_id_weekday_unique');
        });

        DB::statement('ALTER TABLE dealership_opening_hours ADD CONSTRAINT dealership_opening_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE dealership_opening_hours ADD CONSTRAINT dealership_opening_hours_closes_at_check CHECK (closes_at > opens_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealership_opening_hours');
    }
};
