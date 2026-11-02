<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Runs on every `docker compose up` (RUN_SEEDERS), so every seeder must be safe
     * to run again: use updateOrCreate / firstOrCreate, never plain create().
     */
    public function run(): void
    {
        $this->call(AccountSeeder::class);
    }
}
