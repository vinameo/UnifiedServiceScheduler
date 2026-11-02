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
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->onDelete('restrict');
            $table->string('full_name', 100);
            $table->string('phone', 16);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_phone_format_check CHECK (phone ~ '^\+?[0-9]{9,15}$')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
