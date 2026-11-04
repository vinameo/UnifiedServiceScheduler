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
        Schema::create('metric_counters', function (Blueprint $table) {
            $table->string('name', 80);
            $table->string('labels', 200)->default('');
            $table->bigInteger('value')->default(0);
            $table->timestampTz('updated_at');

            $table->primary(['name', 'labels']);
        });

        DB::statement('ALTER TABLE metric_counters ADD CONSTRAINT metric_counters_value_check CHECK (value >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_counters');
    }
};
