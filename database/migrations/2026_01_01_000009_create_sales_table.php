<?php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->float('quantity');
            $table->decimal('total_price', 10, 4);
            $table->mediumBlob('sale_invoice_image')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'driver_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale');
    }
};