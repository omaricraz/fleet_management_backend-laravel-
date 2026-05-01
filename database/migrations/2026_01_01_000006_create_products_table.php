<?php

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->string('item');
            $table->string('type', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->float('unit_volume')->default(0);
            $table->float('unit_weight')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product');
    }
};
