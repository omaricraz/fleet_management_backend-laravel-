<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CarController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\DriverInventoryController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PlatformTenantController;
use App\Http\Controllers\Api\V1\PlatformUserController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\TripController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('platform')->group(function (): void {
        Route::apiResource('tenants', PlatformTenantController::class);
        Route::apiResource('users', PlatformUserController::class)
            ->parameters(['users' => 'id'])
            ->whereNumber('id');
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager'])->group(function (): void {
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('cars', CarController::class);
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('customers', CustomerController::class);
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);

        Route::get('inventory', [InventoryController::class, 'index']);
        Route::get('inventory/alerts', [InventoryController::class, 'alerts']);  //not setup in database. 
        Route::get('cars/{car}/inventory', [InventoryController::class, 'showForCar']);
        Route::post('inventory/opening-balance', [InventoryController::class, 'openingBalance']);
        Route::post('inventory/load', [InventoryController::class, 'load']);
        Route::post('inventory/manual-sale', [InventoryController::class, 'manualSale']);
        Route::post('inventory/close-count', [InventoryController::class, 'closeCount']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager'])->group(function (): void {
        Route::post('inventory/return', [InventoryController::class, 'returnInventory']);

    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin'])->group(function (): void {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::post('inventory/adjustment', [InventoryController::class, 'adjustment']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:driver'])->group(function (): void {
        Route::get('driver/inventory', [DriverInventoryController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager,driver'])->group(function (): void {
        Route::get('trips', [TripController::class, 'index']);
        Route::post('trips', [TripController::class, 'store']);
        Route::get('trips/{trip}', [TripController::class, 'show']);
        Route::patch('trips/{trip}/status', [TripController::class, 'updateStatus']);
        Route::post('trips/{trip}/start', [TripController::class, 'start']);
        Route::post('trips/{trip}/depart', [TripController::class, 'depart']);
        Route::post('trips/{trip}/end', [TripController::class, 'end']);
        Route::delete('trips/{trip}', [TripController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:driver'])->group(function (): void {
        Route::get('requests/my', [RequestController::class, 'my']);
        Route::post('requests', [RequestController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager'])->group(function (): void {
        Route::get('requests', [RequestController::class, 'index']);
        Route::post('requests/{fleet_request}/approve', [RequestController::class, 'approve']);
        Route::post('requests/{fleet_request}/reject', [RequestController::class, 'reject']);
        Route::delete('requests/{fleet_request}', [RequestController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager,driver'])->group(function (): void {
        Route::get('requests/{fleet_request}', [RequestController::class, 'show']);
    });
});
