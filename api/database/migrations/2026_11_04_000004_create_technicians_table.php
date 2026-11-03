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
        Schema::create('technicians', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dealership_id')
                ->constrained('dealerships')
                ->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('level');
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['dealership_id', 'name'], 'technicians_dealership_id_name_unique');
        });

        DB::statement('ALTER TABLE technicians ADD CONSTRAINT technicians_level_check CHECK (level BETWEEN 1 AND 7)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technicians');
    }
};
