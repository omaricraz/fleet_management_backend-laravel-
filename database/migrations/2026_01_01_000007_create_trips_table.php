<?php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->time('arrival_time')->nullable();
            $table->time('departure')->nullable();

            $table->float('volume_capacity')->default(0);
            $table->float('weight_capacity')->default(0);
            $table->float('distance_covered')->default(0);
            $table->string('destination', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'driver_id']);
            $table->index(['tenant_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip');
    }
};
