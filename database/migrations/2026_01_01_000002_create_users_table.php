use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'manager', 'driver'])->default('driver');
            $table->rememberToken();
            $table->boolean('is_platform_admin')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user');
    }
};
