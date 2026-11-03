<?php

use App\Domain\MasterData\BayType;
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
        Schema::create('bays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dealership_id')
                ->constrained('dealerships')
                ->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('name', 60);
            $table->string('bay_type', 16);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['dealership_id', 'code'], 'bays_dealership_id_code_unique');
        });

        $validBayTypes = implode("', '", array_map(fn (BayType $case) => $case->value, BayType::cases()));
        DB::statement("ALTER TABLE bays ADD CONSTRAINT bays_bay_type_check CHECK (bay_type IN ('{$validBayTypes}'))");
        DB::statement('CREATE INDEX bays_dealership_id_bay_type_active_idx ON bays (dealership_id, bay_type) WHERE active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bays');
    }
};
