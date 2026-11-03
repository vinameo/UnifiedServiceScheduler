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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->string('licence_plate', 20);
            $table->string('plate_key', 20)->unique('vehicles_plate_key_unique');
            $table->char('vin', 17)->nullable();
            $table->string('make', 40);
            $table->string('model', 60);
            $table->smallInteger('year')->nullable();
            $table->timestampsTz();

            $table->index('customer_id', 'vehicles_customer_id_index');
        });

        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_plate_key_check CHECK (plate_key ~ '^[A-Z0-9]{4,12}$')");
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_vin_check CHECK (vin IS NULL OR vin ~ '^[A-HJ-NPR-Z0-9]{17}$')");
        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_year_check CHECK (year IS NULL OR (year BETWEEN 1950 AND 2100))');
        DB::statement('CREATE UNIQUE INDEX vehicles_vin_unique ON vehicles (vin) WHERE vin IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
