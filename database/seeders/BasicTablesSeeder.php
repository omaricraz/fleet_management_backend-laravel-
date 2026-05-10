<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BasicTablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();

        /*
        |--------------------------------------------------------------------------
        | Clear basic/system tables
        |--------------------------------------------------------------------------
        */

        DB::table('personal_access_tokens')->truncate();

        // Optional Laravel default tables
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->truncate();
        }

        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->truncate();
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->truncate();
        }

        if (Schema::hasTable('cache')) {
            DB::table('cache')->truncate();
        }

        if (Schema::hasTable('cache_locks')) {
            DB::table('cache_locks')->truncate();
        }

        /*
        |--------------------------------------------------------------------------
        | Example default admin/user seed
        |--------------------------------------------------------------------------
        */

        /*
        DB::table('users')->insert([
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        */

        // Enable foreign key checks
        Schema::enableForeignKeyConstraints();
    }
}