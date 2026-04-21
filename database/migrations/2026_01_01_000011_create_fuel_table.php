
return new class extends Migration {
    public function up(): void
    {
        Schema::create('fuel', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();

            $table->decimal('current_fuel', 10, 4)->default(0);
            $table->dateTime('refill_date')->nullable();
            $table->float('fuel_price_per_l')->default(0);
            $table->decimal('cost', 10, 4)->default(0);

            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel');
    }
};