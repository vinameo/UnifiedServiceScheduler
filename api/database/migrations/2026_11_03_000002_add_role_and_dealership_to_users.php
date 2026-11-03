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
        if (DB::table('users')->count() > 0) {
            throw new RuntimeException('Cannot migrate users: existing rows found. Accounts must be migrated before adding role and dealership.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32);
            $table->foreignUuid('dealership_id')
                ->nullable()
                ->constrained('dealerships')
                ->onDelete('restrict');

            $table->index(['dealership_id', 'role'], 'users_dealership_id_role_idx');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('CUSTOMER', 'SERVICE_ADVISOR', 'WORKSHOP_FOREMAN', 'ADMIN'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_staff_has_dealership_check CHECK ((role = \'CUSTOMER\') = (dealership_id IS NULL))');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_lowercase_check CHECK (email = lower(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_staff_has_dealership_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_lowercase_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_dealership_id_role_idx');
            $table->dropForeign(['dealership_id']);
            $table->dropColumn(['role', 'dealership_id']);
        });
    }
};
