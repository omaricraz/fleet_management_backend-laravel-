return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_transaction', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();

            $table->float('quantity');
            $table->enum('type', ['load', 'sale', 'adjustment']);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transaction');
    }
};