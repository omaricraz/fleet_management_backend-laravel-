<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subscription_plan', 50)->nullable();
            $table->string('logo', 500)->nullable();
            $table->char('main_color', 6)->nullable();
            $table->char('bg_color', 6)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant');
    }
};