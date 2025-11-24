<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UsersController;
use App\Http\Controllers\Cabins\CabinController;

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
    'middleware' => ['api','auth:api',],
], function ($router) {
    Route::resource('role', RoleController::class);
    Route::resource('users', UsersController::class);
    Route::apiResource('cabins', CabinController::class);

});