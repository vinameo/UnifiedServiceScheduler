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
        Schema::create('service_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dealership_id')
                ->constrained('dealerships')
                ->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 100);
            $table->string('description', 200)->nullable();
            $table->integer('duration_minutes');
            $table->smallInteger('required_level');
            $table->string('required_bay_type', 16);
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['dealership_id', 'code'], 'service_types_dealership_id_code_unique');
        });

        DB::statement('ALTER TABLE service_types ADD CONSTRAINT service_types_duration_minutes_check CHECK (duration_minutes > 0 AND duration_minutes % 15 = 0 AND duration_minutes <= 480)');
        DB::statement('ALTER TABLE service_types ADD CONSTRAINT service_types_required_level_check CHECK (required_level BETWEEN 1 AND 7)');

        $validBayTypes = implode("', '", array_map(fn (BayType $case) => $case->value, BayType::cases()));
        DB::statement("ALTER TABLE service_types ADD CONSTRAINT service_types_required_bay_type_check CHECK (required_bay_type IN ('{$validBayTypes}'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
