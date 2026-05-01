<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('plate_number', 50)->unique();
            $table->float('overall_volume_capacity')->default(0);
            $table->float('overall_weight_capacity')->default(0);
            $table->float('fuel_efficiency')->default(0);
            $table->string('color', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'plate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car');
    }
};