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
    Route::resource('role', RoleController::class)->middleware([
        'index' => 'permission:list_role',
        'show' => 'permission:list_role',
        'store' => 'permission:create_role',
        'update' => 'permission:edit_role',
        'destroy' => 'permission:delete_role',
    ]);
});
Route::middleware(['auth:api'])->group(function () {

    Route::apiResource('cabins', CabinController::class)->middleware([
        'index' => 'permission:list_cabin',
        'show' => 'permission:show_cabin_details',
        'store' => 'permission:create_cabin',
        'update' => 'permission:edit_cabin',
        'destroy' => 'permission:delete_cabin',
    ]);
    Route::put('/cabins/{id}', CabinController::class . '@update')->middleware('permission:edit_cabin');
    Route::post('/cabins/{id}/features', [CabinController::class, 'assignFeatures'])->middleware('permission:edit_cabin');
    Route::post('/cabins/{id}/images', [CabinController::class, 'uploadImages'])->middleware('permission:edit_cabin');
    Route::post('/cabins/{cabin}/price', [CabinPriceController::class, 'calculate'])->middleware('permission:show_cabin_details');
    Route::get('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'index'])->middleware('permission:show_cabin_details');
    Route::post('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'store'])->middleware('permission:edit_cabin');
    // actualizar regla
    Route::put('/price-rules/{priceRule}', [CabinPriceRuleController::class, 'update'])->middleware('permission:edit_cabin');
    // eliminar regla
    Route::delete('/price-rules/{priceRule}', [CabinPriceRuleController::class, 'destroy'])->middleware('permission:delete_cabin');
    Route::apiResource('reservations', ReservationController::class)->middleware([
        'index' => 'permission:list_reservation',
        'show' => 'permission:show_reservation_details',
        'store' => 'permission:create_reservation',
        'update' => 'permission:edit_reservation',
        'destroy' => 'permission:cancel_reservation',
    ]);
    Route::apiResource('features', FeatureController::class);
    Route::apiResource('users', UsersController::class)->middleware([
        'index' => 'permission:list_staff',
        'show' => 'permission:list_staff',
        'store' => 'permission:create_staff',
        'update' => 'permission:edit_staff',
        'destroy' => 'permission:delete_staff',
    ]);

    // Cabin images
    Route::get('/cabins/{id}/images', [CabinImageController::class, 'index'])->middleware('permission:show_cabin_details');
    Route::post('/cabins/{id}/images', [CabinImageController::class, 'store'])->middleware('permission:edit_cabin');
    Route::delete('/cabins/{id}/images/{imageId}', [CabinImageController::class, 'destroy'])->middleware('permission:delete_cabin');
});
