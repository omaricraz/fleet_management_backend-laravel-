return new class extends Migration {
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['fuel', 'maintenance', 'inventory']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->longText('notes')->nullable();
            $table->string('maintenance_requested')->nullable();
            $table->decimal('fuel_requested', 10, 5)->nullable();
            $table->decimal('litre_cost', 10, 5)->nullable();
            $table->mediumBlob('invoice_image')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request');
    }
};