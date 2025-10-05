<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Kpi\KpiController;
use App\Http\Controllers\Rol\RoleController;
use App\Http\Controllers\Pets\PetsController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\Surgerie\SurgerieController;
use App\Http\Controllers\MedicalRecord\PaymentController;
use App\Http\Controllers\Appointment\AppointmentController;
use App\Http\Controllers\Vaccination\VaccinationController;
use App\Http\Controllers\Veterinarie\VeterinarieController;
use App\Http\Controllers\MedicalRecord\MedicalRecordController;
 
Route::group([
    // 'middleware' => 'api',
    'prefix' => 'auth',
    // 'middleware' => ['auth:api']//,'permission:edit articles'
], function ($router) {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');//->middleware('auth:api')
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');//->middleware('auth:api')
    Route::post('/me', [AuthController::class, 'me'])->name('me');//->middleware('auth:api')
});
Route::group([
    'middleware' => ['auth:api']
],function($router) {
    Route::resource("role",RoleController::class);
    Route::post("staffs/{id}",[StaffController::class,"update"]);
    Route::resource("staffs",StaffController::class);

    Route::get("veterinaries/config",[VeterinarieController::class,"config"]);
    Route::post("veterinaries/{id}",[VeterinarieController::class,"update"]);
    Route::resource("veterinaries",VeterinarieController::class);

    Route::post("pets/{id}",[PetsController::class,"update"]);
    Route::resource("pets",PetsController::class);

    Route::get("appointments/search-pets/{search}",[AppointmentController::class,"searchPets"]);
    Route::post("appointments/filter-availability",[AppointmentController::class,"filter"]);
    Route::post("appointments/index",[AppointmentController::class,"index"]);
    Route::resource("appointments",AppointmentController::class);

    Route::group(['middleware' => ['permission:calendar']], function () {
        Route::get("/medical-records/calendar",[MedicalRecordController::class,"calendar"]);
        Route::put("/medical-records/update_aux/{id}",[MedicalRecordController::class,"update_aux"]);
    });
    Route::group(['middleware' => ['permission:show_medical_records']], function () {
        Route::post("/medical-records/pet",[MedicalRecordController::class,"index"]);
    });
    
    Route::post("vaccinations/index",[VaccinationController::class,"index"]);
    Route::resource("vaccinations",VaccinationController::class);

    Route::post("surgeries/index",[SurgerieController::class,"index"]);
    Route::resource("surgeries",SurgerieController::class);

    
    Route::group(['middleware' => ['permission:show_payment|edit_payment']], function () {
        Route::post("payments/index",[PaymentController::class,"index"]);
        Route::resource("payments",PaymentController::class);
    });

    Route::group(['middleware' => ['permission:show_report_grafics']], function () {
        Route::post("kpi_report_general",[KpiController::class,"kpi_report_general"]);
        Route::post("kpi_veterinarie_net_income",[KpiController::class,"kpi_veterinarie_net_income"]);
        Route::post("kpi_veterinarie_most_asigned",[KpiController::class,"kpi_veterinarie_most_asigned"]);
        Route::post("kpi_total_bruto",[KpiController::class,"kpi_total_bruto"]);
        Route::post("kpi_report_for_servicies",[KpiController::class,"kpi_report_for_servicies"]);
        Route::post("kpi_pets_most_payments",[KpiController::class,"kpi_pets_most_payments"]);
        Route::post("kpi_payments_x_day_month",[KpiController::class,"kpi_payments_x_day_month"]);
        Route::post("kpi_payments_x_month_of_year",[KpiController::class,"kpi_payments_x_month_of_year"]);
    });

});
Route::get("appointment-excel",[AppointmentController::class,"downloadExcel"]);
Route::get("vaccination-excel",[VaccinationController::class,"downloadExcel"]);
Route::get("surgeries-excel",[SurgerieController::class,"downloadExcel"]);
Route::get("payments-excel",[PaymentController::class,"downloadExcel"]);