<?php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();

            $table->string('full_name');
            $table->string('phone', 50)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer');
    }
};