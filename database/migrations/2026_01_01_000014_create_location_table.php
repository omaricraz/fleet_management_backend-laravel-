return new class extends Migration {
    public function up(): void
    {
        Schema::create('location', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 5, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('accuracy', 5, 2)->nullable();

            $table->dateTime('recorded_at');

            $table->index(['car_id', 'recorded_at']);
            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location');
    }
};