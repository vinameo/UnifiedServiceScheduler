<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dealerships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('branch_name', 120)->nullable();
            $table->string('timezone', 64);
            $table->string('phone', 32);
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dealerships');
    }
};
