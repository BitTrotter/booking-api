<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Cabins\CabinController;
use App\Http\Controllers\Features\FeatureController;
use App\Http\Controllers\Reservations\ReservationController;
use App\Http\Controllers\Users\UsersController;
use App\Http\Controllers\Cabins\CabinImageController;
use App\Http\Controllers\Cabins\CabinPriceController;
use App\Http\Controllers\Cabins\CabinPriceRuleController;


Route::group([
    'middleware' => 'api',
    'prefix' => 'auth',
    // 'middleware' => ['auth:api','permission:publish articles']
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('/me', [AuthController::class, 'me'])->name('me');
});
Route::group([
    'middleware' => ['api', 'auth:api'],
], function ($router) {
    Route::resource('role', RoleController::class);
});
Route::middleware(['auth:api'])->group(function () {

    Route::apiResource('cabins', CabinController::class);
    Route::put('/cabins/{id}', CabinController::class . '@update');
    Route::post('/cabins/{id}/features', [CabinController::class, 'assignFeatures']);
    Route::post('/cabins/{id}/images', [CabinController::class, 'uploadImages']);
    Route::post('/cabins/{cabin}/price', [CabinPriceController::class, 'calculate']);
    Route::get('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'index']);
    Route::post('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'store'] );
    // actualizar regla
    Route::put('/price-rules/{priceRule}',[CabinPriceRuleController::class, 'update'] );
    // eliminar regla
    Route::delete( '/price-rules/{priceRule}', [CabinPriceRuleController::class, 'destroy']);
    Route::apiResource('reservations', ReservationController::class);
    Route::apiResource('features', FeatureController::class);
    Route::apiResource('users', UsersController::class);

    // Cabin images
    Route::get('/cabins/{id}/images', [CabinImageController::class, 'index']);
    Route::post('/cabins/{id}/images', [CabinImageController::class, 'store']);
    Route::delete('/cabins/{id}/images/{imageId}', [CabinImageController::class, 'destroy']);
});
