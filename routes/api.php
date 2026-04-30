<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Cabins\CabinController;
use App\Http\Controllers\Features\FeatureController;
use App\Http\Controllers\Reservations\ReservationController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Permissions\PermissionController;
use App\Http\Controllers\Users\UsersController;
use App\Http\Controllers\Cabins\CabinImageController;
use App\Http\Controllers\Cabins\CabinPriceController;
use App\Http\Controllers\Cabins\CabinPriceRuleController;


// Stripe webhook — sin auth, Stripe verifica con firma HMAC
Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

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
Route::middleware(['api', 'auth:api'])->group(function () {
    Route::get('/permissions',          [PermissionController::class, 'index'])->middleware('permission:list_role,api');
    Route::post('/permissions',         [PermissionController::class, 'store'])->middleware('permission:create_role,api');
    Route::delete('/permissions/{id}',  [PermissionController::class, 'destroy'])->middleware('permission:delete_role,api');

    Route::get('/role',          [RoleController::class, 'index'])->middleware('permission:list_role,api');
    Route::post('/role',         [RoleController::class, 'store'])->middleware('permission:create_role,api');
    Route::get('/role/{role}',   [RoleController::class, 'show'])->middleware('permission:list_role,api');
    Route::put('/role/{role}',   [RoleController::class, 'update'])->middleware('permission:edit_role,api');
    Route::delete('/role/{role}',[RoleController::class, 'destroy'])->middleware('permission:delete_role,api');
});
Route::middleware(['auth:api'])->group(function () {

    Route::get('/cabins',           [CabinController::class, 'index'])->middleware('permission:list_cabin,api');
    Route::post('/cabins',          [CabinController::class, 'store'])->middleware('permission:create_cabin,api');
    Route::get('/cabins/{cabin}',   [CabinController::class, 'show'])->middleware('permission:show_cabin_details,api');
    Route::put('/cabins/{cabin}',   [CabinController::class, 'update'])->middleware('permission:edit_cabin,api');
    Route::patch('/cabins/{cabin}', [CabinController::class, 'update'])->middleware('permission:edit_cabin,api');
    Route::delete('/cabins/{cabin}',[CabinController::class, 'destroy'])->middleware('permission:delete_cabin,api');
    Route::post('/cabins/{id}/features', [CabinController::class, 'assignFeatures'])->middleware('permission:edit_cabin,api');
    Route::post('/cabins/{cabin}/price', [CabinPriceController::class, 'calculate'])->middleware('permission:show_cabin_details,api');
    Route::get('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'index'])->middleware('permission:show_cabin_details,api');
    Route::post('/cabins/{cabin}/price-rules', [CabinPriceRuleController::class, 'store'])->middleware('permission:edit_cabin,api');
    Route::put('/price-rules/{priceRule}', [CabinPriceRuleController::class, 'update'])->middleware('permission:edit_cabin,api');
    Route::delete('/price-rules/{priceRule}', [CabinPriceRuleController::class, 'destroy'])->middleware('permission:delete_cabin,api');
    Route::get('/reservations/availability', [ReservationController::class, 'checkAvailability']);
    Route::get('/reservations',                  [ReservationController::class, 'index'])->middleware('permission:list_reservation,api');
    Route::post('/reservations',                 [ReservationController::class, 'store'])->middleware('permission:create_reservation,api');
    Route::get('/reservations/{reservation}',    [ReservationController::class, 'show'])->middleware('permission:show_reservation_details,api');
    Route::put('/reservations/{reservation}',    [ReservationController::class, 'update'])->middleware('permission:edit_reservation,api');
    Route::patch('/reservations/{reservation}',  [ReservationController::class, 'update'])->middleware('permission:edit_reservation,api');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->middleware('permission:cancel_reservation,api');

    Route::apiResource('features', FeatureController::class);

    Route::get('/users',           [UsersController::class, 'index'])->middleware('permission:list_staff,api');
    Route::post('/users',          [UsersController::class, 'store'])->middleware('permission:create_staff,api');
    Route::get('/users/{user}',    [UsersController::class, 'show'])->middleware('permission:list_staff,api');
    Route::put('/users/{user}',    [UsersController::class, 'update'])->middleware('permission:edit_staff,api');
    Route::patch('/users/{user}',  [UsersController::class, 'update'])->middleware('permission:edit_staff,api');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->middleware('permission:delete_staff,api');

    // Cabin images
    Route::get('/cabins/{id}/images', [CabinImageController::class, 'index'])->middleware('permission:show_cabin_details,api');
    Route::post('/cabins/{id}/images', [CabinImageController::class, 'store'])->middleware('permission:edit_cabin,api');
    Route::delete('/cabins/{id}/images/{imageId}', [CabinImageController::class, 'destroy'])->middleware('permission:delete_cabin,api');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments/intent', [PaymentController::class, 'createIntent']);
    Route::get('/payments/{reservation_id}', [PaymentController::class, 'show']);
});
