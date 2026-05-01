<?php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();

            $table->float('quantity')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['car_id', 'product_id']);
            $table->index(['tenant_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
