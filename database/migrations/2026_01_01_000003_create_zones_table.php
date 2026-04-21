return new class extends Migration {
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('city', 30);
            $table->integer('number_of_stores')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zone');
    }
};

