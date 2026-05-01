<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenant')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
