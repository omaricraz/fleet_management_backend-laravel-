return new class extends Migration {
    public function up(): void
    {
        Schema::create('maintenance', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('car_id')->constrained('cars')->cascadeOnDelete();

            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed'])->default('pending');
            $table->string('service')->nullable();
            $table->mediumBlob('sparepart_invoice_image')->nullable();
            $table->string('garage')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->dateTime('service_start_date')->nullable();
            $table->dateTime('service_end_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'car_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance');
    }
};