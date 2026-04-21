return new class extends Migration {
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key_name');
            $table->text('value')->nullable();
            $table->string('type', 50)->nullable();
            $table->string('scope', 50)->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->timestamps();

            $table->unique(['key_name', 'scope', 'user_id', 'tenant_id'], 'unique_setting');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};