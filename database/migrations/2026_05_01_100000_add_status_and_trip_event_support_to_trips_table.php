<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->string('status', 32)->default('ready')->after('destination');
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->dateTime('arrival_time')->nullable()->change();
            $table->dateTime('departure')->nullable()->change();
        });

        if (Schema::hasTable('trips')) {
            DB::table('trips')->whereNotNull('end_date')->update(['status' => 'idle']);
            DB::table('trips')
                ->whereNull('end_date')
                ->whereNotNull('start_date')
                ->update(['status' => 'loading']);
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn('status');
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->time('arrival_time')->nullable()->change();
            $table->time('departure')->nullable()->change();
        });
    }
};
