<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated schema matching the provided SQL (MySQL-oriented).
 *
 * If earlier domain migrations (2026_01_01_*, etc.) already ran, drop or archive those
 * before running this on the same database—otherwise table-already-exists errors occur.
 */
final class OnlyMigrationFileYouNeed extends Migration
{
    public function up(): void
    {
        Schema::create('tenant', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('subscription_plan', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->string('logo', 500)->nullable();
            $table->char('main_color', 6)->nullable();
            $table->char('bg_color', 6)->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->enum('role', ['admin', 'driver', 'manager']);
            $table->boolean('is_platform_admin')->default(false);

            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key_name');
            $table->text('value')->nullable();
            $table->string('type', 50)->nullable();
            $table->string('scope', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->unique(['key_name', 'scope', 'user_id', 'tenant_id'], 'unique_setting');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('item');
            $table->string('type', 100)->nullable();
            $table->decimal('price', 10, 2);
            $table->float('unit_volume')->nullable();
            $table->float('unit_weight')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('cars', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('model', 100)->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->float('overall_volume_capacity')->nullable();
            $table->float('overall_weight_capacity')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->float('fuel_efficiency')->nullable();
            $table->string('color', 30)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('zones', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('number_of_stores')->nullable();
            $table->string('name')->nullable();
            $table->string('city', 30)->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('drivers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('full_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('zone_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('trips', function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('start_date')->useCurrent();
            $table->dateTime('end_date')->nullable();
            $table->time('arrival_time')->nullable();
            $table->time('departure')->nullable();
            $table->float('volume_capacity')->nullable();
            $table->float('weight_capacity')->nullable();
            $table->float('distance_covered')->nullable();
            $table->string('destination', 500)->nullable();
            $table->enum('status', ['active', 'closed']);
            $table->unsignedInteger('zone_id')->nullable();
            $table->unsignedInteger('driver_id')->nullable();
            $table->unsignedInteger('car_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('driver_id')->references('id')->on('drivers');
            $table->foreign('car_id')->references('id')->on('cars');
            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('trip_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('trip_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->enum('event_type', ['active', 'closed', 'sale']);
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->json('metadata')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('product_id')->references('id')->on('products');
        });

        Schema::create('fuel', function (Blueprint $table): void {
            $table->increments('id');
            $table->decimal('current_fuel', 10, 4)->nullable();
            $table->dateTime('refill_date')->nullable();
            $table->float('fuel_price_per_l')->nullable();
            $table->decimal('cost', 10, 9)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected']);
            $table->unsignedInteger('trip_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->unsignedInteger('car_id')->nullable();
            $table->unsignedInteger('driver_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('car_id')->references('id')->on('cars');
            $table->foreign('driver_id')->references('id')->on('drivers');
        });

        Schema::create('inventory', function (Blueprint $table): void {
            $table->increments('id');
            $table->float('quantity')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('car_id')->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('car_id')->references('id')->on('cars');
            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('tenant_id')->references('id')->on('tenant');

            $table->unique(['car_id', 'product_id', 'tenant_id']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('full_name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->unsignedInteger('zone_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('trip_id')->references('id')->on('trips');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->increments('id');
            $table->float('quantity')->nullable();
            $table->decimal('total_price', 10, 4)->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->mediumBlob('sale_invoice_image')->nullable();
            $table->unsignedInteger('driver_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('driver_id')->references('id')->on('drivers');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('inventory_transaction', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('car_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->float('quantity')->nullable();
            $table->enum('type', ['opening', 'load', 'sale', 'return', 'adjustment', 'closing']);
            $table->unsignedInteger('trip_id')->nullable();
            $table->unsignedInteger('sale_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->longText('notes')->nullable();
            $table->float('actual_quantity')->nullable();
            $table->float('expected_quantity')->nullable();
            $table->float('variance')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->float('before_qty')->nullable();
            $table->float('after_qty')->nullable();

            $table->foreign('car_id')->references('id')->on('cars');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('tenant_id')->references('id')->on('tenant');
        });

        Schema::create('maintenance', function (Blueprint $table): void {
            $table->increments('id');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'completed']);
            $table->string('service')->nullable();
            $table->mediumBlob('sparepart_invoice_image')->nullable();
            $table->string('garage')->nullable();
            $table->decimal('cost', 10, 9)->nullable();
            $table->dateTime('service_start_date')->nullable();
            $table->dateTime('service_end_date')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->unsignedInteger('car_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('car_id')->references('id')->on('cars');
        });

        Schema::create('location', function (Blueprint $table): void {
            $table->increments('id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('speed', 5, 2)->nullable();
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('accuracy', 5, 2)->nullable();
            $table->dateTime('recorded_at')->nullable();
            $table->unsignedInteger('tenant_id')->nullable();
            $table->unsignedInteger('trip_id')->nullable();
            $table->unsignedInteger('car_id')->nullable();
            $table->unsignedInteger('driver_id')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenant');
            $table->foreign('trip_id')->references('id')->on('trips');
            $table->foreign('car_id')->references('id')->on('cars');
            $table->foreign('driver_id')->references('id')->on('drivers');
        });

        Schema::create('requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->enum('type', ['fuel', 'maintenance', 'inventory']);
            $table->unsignedInteger('driver_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected']);
            $table->longText('notes')->nullable();
            $table->string('maintenance_requested')->nullable();
            $table->decimal('fuel_requested', 10, 5)->nullable();
            $table->decimal('cost', 10, 5)->nullable();
            $table->mediumBlob('invoice_image')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('driver_id')->references('id')->on('drivers');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('requests');
        Schema::dropIfExists('location');
        Schema::dropIfExists('maintenance');
        Schema::dropIfExists('inventory_transaction');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('fuel');
        Schema::dropIfExists('trip_events');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('products');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenant');

        Schema::enableForeignKeyConstraints();
    }
}

return new OnlyMigrationFileYouNeed();
