<?php

namespace App\Http\Controllers\Kpi;

use Carbon\Carbon;
use App\Models\Pets\Pet;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Surgerie\Surgerie;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Appointment\Appointment;
use App\Models\Vaccination\Vaccination;
use App\Models\Surgerie\SurgeriePayment;
use App\Models\Appointment\AppointmentPayment;
use App\Models\Vaccination\VaccinationPayment;

class KpiController extends Controller
{
    public function kpi_report_general(Request $request){
        $year = date("Y");
        $month = date("m");//01 - 1 = 0 12 2024 = //2025 0

        $n_pets = Pet::whereYear("created_at",$year)->whereMonth("created_at",$month)->count();

        $net_income_appointments = AppointmentPayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_surgerie = SurgeriePayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_vaccination = VaccinationPayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_total_current = $net_income_appointments + $net_income_surgerie + $net_income_vaccination;//INGRESOS NETOS TOTAL DEL MES ACTUAL

        $month_before = Carbon::parse($year."-".$month."-01")->subMonth();//2025-01-01 2024-12-01
        $net_income_appointments_before = AppointmentPayment::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $net_income_surgeries_before = SurgeriePayment::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $net_income_vaccination_before = VaccinationPayment::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $net_income_total_before = $net_income_appointments_before + $net_income_surgeries_before + $net_income_vaccination_before;//INGRESOS NETOS TOTAL DEL MES ANTERIOR
        
        $variation_percentage = (($net_income_total_current - $net_income_total_before)/$net_income_total_before)*100;
        // N° DE MASCOTITAS REGISTRADAS EN EL MES ACTUAL
        // LOS INGRESOS NETOS DEL MES ACTUAL
        // DE LA VARIACIÓN PORCENTUAL DEL MES ANTERIOR (total_current - total_before)/total_before * 100
        // N° DE SERVICIOS REGISTRADOS EN EL MES (VACUNA,CITAS,CIRUJIA)

        $n_service_record = MedicalRecord::whereYear("created_at",$year)->whereMonth("created_at",$month)->count();
        return response()->json([
            "n_pets" => $n_pets,
            "n_service_record" => $n_service_record,
            "variation_percentage" => round($variation_percentage,2),
            "net_income_total_before" => $net_income_total_before,
            "net_income_total" => $net_income_total_current,
        ]);
    }

    public function kpi_veterinarie_net_income(Request $request){
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        // EL VETERINARIO CON MAS INGRESOS NETOS POR LOS SERVICOS ASIGNADOS
        $year = date("Y");
        $month = date("m");
        $veterinaries_net_income = collect([]);
        // OBTENER EL VETERINARIO CON MAS INGRESOS NETOS A NIVEL DE CITAS MEDICAS
        $appointments_veterinaries = DB::table("appointment_payments")->where("appointment_payments.deleted_at",NULL)
                                        ->join("appointments","appointments.id","=","appointment_payments.appointment_id")
                                        ->join("users","users.id","=","appointments.veterinarie_id")
                                        ->where("appointments.deleted_at",NULL)
                                        ->whereYear("appointment_payments.created_at",$year)
                                        ->whereMonth("appointment_payments.created_at",$month)
                                        ->selectRaw("appointments.veterinarie_id,(users.name || ' ' || users.surname) as full_name,users.gender, CAST(SUM(appointment_payments.amount) AS DOUBLE PRECISION) as net_income, count(*) as count_payments")
                                        ->groupBy("appointments.veterinarie_id","full_name","users.gender")
                                        ->orderBy("net_income","desc")
                                        ->first();

        $veterinaries_net_income->push($appointments_veterinaries); 
        // OBTENER EL VETERINARIO CON MAS INGRESOS NETOS A NIVEL DE VACUNAS
        $vaccination_veterinaries = DB::table("vaccination_payments")->where("vaccination_payments.deleted_at",NULL)
                                        ->join("vaccinations","vaccinations.id","=","vaccination_payments.vaccination_id")
                                        ->join("users","users.id","=","vaccinations.veterinarie_id")
                                        ->where("vaccinations.deleted_at",NULL)
                                        ->whereYear("vaccination_payments.created_at",$year)
                                        ->whereMonth("vaccination_payments.created_at",$month)
                                        ->selectRaw("vaccinations.veterinarie_id,(users.name || ' ' || users.surname) as full_name,users.gender, CAST(SUM(vaccination_payments.amount) AS DOUBLE PRECISION) as net_income, count(*) as count_payments")
                                        ->groupBy("vaccinations.veterinarie_id","full_name","users.gender")
                                        ->orderBy("net_income","desc")
                                        ->first();                                
        $veterinaries_net_income->push($vaccination_veterinaries);                                                 
        // OBTENER EL VETERINARIO CON MAS INGRESOS NETOS A NIVEL DE CIRUJIA
        $surgerie_veterinaries = DB::table("surgerie_payments")->where("surgerie_payments.deleted_at",NULL)
                                        ->join("surgeries","surgeries.id","=","surgerie_payments.surgerie_id")
                                        ->join("users","users.id","=","surgeries.veterinarie_id")
                                        ->where("surgeries.deleted_at",NULL)
                                        ->whereYear("surgerie_payments.created_at",$year)
                                        ->whereMonth("surgerie_payments.created_at",$month)
                                        ->selectRaw("surgeries.veterinarie_id,(users.name || ' ' || users.surname) as full_name,users.gender, CAST(SUM(surgerie_payments.amount) AS DOUBLE PRECISION) as net_income, count(*) as count_payments")
                                        ->groupBy("surgeries.veterinarie_id","full_name","users.gender")
                                        ->orderBy("net_income","desc")
                                        ->first();                                
        $veterinaries_net_income->push($surgerie_veterinaries);      
        
        $veterinaries_group = collect([]);
        foreach ($veterinaries_net_income->groupBy("veterinarie_id") as $key => $veterinaries) {
            $veterinaries_group->push([
                "veterinarie_id" => $key,
                "full_name" => $veterinaries[0]->full_name,
                "gender" => $veterinaries[0]->gender,
                "net_income_total" => $veterinaries->sum("net_income")
            ]);
        }
        $veterinarie_most_net_income =  $veterinaries_group->isNotEmpty() ? $veterinaries_group->sortByDesc("net_income_total")->first() : null;
        
        $veterinarie_id = $veterinarie_most_net_income ? $veterinarie_most_net_income["veterinarie_id"] :null;
        $variation_percentage = 0;$net_income_total_before = 0;
        if($veterinarie_id){
            $month_before = Carbon::parse($year."-".$month."-01")->subMonth();//2025-01-01 2024-12-01
            $net_income_appointments_before = AppointmentPayment::whereYear("created_at",$month_before->format("Y"))
                                                ->whereMonth("created_at",$month_before->format("m"))
                                                ->whereHas("appointment",function($q) use($veterinarie_id){
                                                    $q->where("veterinarie_id",$veterinarie_id);
                                                })
                                                ->sum("amount");
                                                
            $net_income_surgeries_before = SurgeriePayment::whereYear("created_at",$month_before->format("Y"))
                                                ->whereMonth("created_at",$month_before->format("m"))
                                                ->whereHas("surgerie",function($q) use($veterinarie_id){
                                                    $q->where("veterinarie_id",$veterinarie_id);
                                                })
                                                ->sum("amount");
                                                
            $net_income_vaccination_before = VaccinationPayment::whereYear("created_at",$month_before->format("Y"))
                                                ->whereMonth("created_at",$month_before->format("m"))
                                                ->whereHas("vaccination",function($q) use($veterinarie_id){
                                                    $q->where("veterinarie_id",$veterinarie_id);
                                                })
                                                ->sum("amount");

            $net_income_total_before = $net_income_appointments_before + $net_income_surgeries_before + $net_income_vaccination_before;//INGRESOS NETOS TOTAL DEL MES ANTERIOR
            
            $variation_percentage = (($veterinarie_most_net_income["net_income_total"] - $net_income_total_before)/$net_income_total_before)*100;
        }
        
        return response()->json([
            "year" => date("Y"),
            "month_name" => Carbon::now()->monthName,
            "variation_percentage" => round($variation_percentage,2),
            "net_income_total_before" => $net_income_total_before,
            "veterinarie_most_net_income" => $veterinarie_most_net_income,
        ]); 
    }

    public function kpi_veterinarie_most_asigned(Request $request){
        $year = date("Y");
        $month = date("m");

        $veterinarie_most_asigned = DB::table("medical_records")->where("medical_records.deleted_at",NULL)
                                    ->join("users","medical_records.veterinarie_id","=","users.id")
                                    ->whereYear("medical_records.created_at",$year)
                                    ->whereMonth("medical_records.created_at",$month)
                                    ->selectRaw("(users.name || ' ' || users.surname )as full_name,users.gender, medical_records.veterinarie_id,count(*) as n_asigned")
                                    ->groupBy("veterinarie_id","full_name","users.gender")
                                    ->orderBy("n_asigned","desc")
                                    ->first();

        $month_before = Carbon::parse($year."-".$month."-01")->subMonth();
        $n_most_asigned_before = 0; $variation_percentage = 0;
        if($veterinarie_most_asigned){
            $n_most_asigned_before = DB::table("medical_records")->where("medical_records.deleted_at",NULL)
                                        ->join("users","medical_records.veterinarie_id","=","users.id")
                                        ->whereYear("medical_records.created_at",$month_before->format("Y"))
                                        ->whereMonth("medical_records.created_at",$month_before->format("m"))
                                        ->where("medical_records.veterinarie_id",$veterinarie_most_asigned->veterinarie_id)
                                        ->count();
            $variation_percentage = (($veterinarie_most_asigned->n_asigned - $n_most_asigned_before)/$n_most_asigned_before)*100;
        }
        return response()->json([
            "variation_percentage" => round($variation_percentage,2),
            "n_most_asigned_before" => $n_most_asigned_before,
            "veterinarie_most_asigned" => $veterinarie_most_asigned,
        ]);
    }

    public function kpi_total_bruto(Request $request) {
        $year = date("Y");
        $month = date("m");

        // TOTAL BRUTO DE CADA UNO DE LOS SERVICIOS
        $total_bruto_appointments = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
        $total_bruto_vaccinations = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
        $total_bruto_surgeries = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
        $total_bruto_general = $total_bruto_appointments + $total_bruto_vaccinations + $total_bruto_surgeries;

        // TOTAL PAGADO DE CADA UNO DE LOS SERVICIOS (LOS INGRESOS NETO)
        $net_income_appointments = AppointmentPayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_surgerie = SurgeriePayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_vaccination = VaccinationPayment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount"); 
        $net_income_total_current = $net_income_appointments + $net_income_surgerie + $net_income_vaccination;//INGRESOS NETOS TOTAL DEL MES ACTUAL
        $percentage_payments = (($net_income_total_current)/$total_bruto_general)*100;

        // TOTAL DE DEUDA DE CADA UNO DE LOS SERVICIOS
        $total_not_payments = $total_bruto_general - $net_income_total_current;
        $percentage_not_payments = (($total_not_payments)/$total_bruto_general)*100;

        // TOTAL BRUTO DE CADA UNO DE LOS SERVICIOS PERO DEL MES ANTERIOR

        $month_before = Carbon::parse($year."-".$month."-01")->subMonth();

        $total_bruto_appointments_before = Appointment::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $total_bruto_vaccinations_before = Vaccination::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $total_bruto_surgeries_before = Surgerie::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
        $total_bruto_general_before = $total_bruto_appointments_before + $total_bruto_vaccinations_before + $total_bruto_surgeries_before;

        $variation_percentage = (($total_bruto_general - $total_bruto_general_before)/$total_bruto_general_before)*100;

        return response()->json([
            "variation_percentage" => round($variation_percentage,2),
            "total_bruto_general_before" => $total_bruto_general_before,
            "percentage_not_payments" => round($percentage_not_payments,2),
            "total_not_payments" => $total_not_payments,
            "percentage_payments" => round($percentage_payments,2),
            "total_payments" => $net_income_total_current,
            "total_bruto_general" => $total_bruto_general,
            "total_bruto_surgeries" => $total_bruto_surgeries,
            "total_bruto_vaccinations" => $total_bruto_vaccinations,
            "total_bruto_appointments" => $total_bruto_appointments,
        ]);
    }

    public function kpi_report_for_servicies(Request $request){
        $year = date("Y");
        $month = date("m");

        $month_before = Carbon::parse($year."-".$month."-01")->subMonth();

        // REPORTE GENERAL DE CITAS MEDICAS
            // LOS INGRESOS BRUTOS
        $bruto_income_appoinments = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
            // VARIACIÓN PORCENTUAL EN BASE AL MES ANTERIOR
        $bruto_income_appoinments_before = Appointment::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
            $VPAppointments = (($bruto_income_appoinments - $bruto_income_appoinments_before)/$bruto_income_appoinments_before)*100;    
            //N° DE CITAS PROGRAMADAS
        $n_appointments_total = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->count();
        $n_appointments_attend = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",3)->count();
        $n_appointments_cancel = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",2)->count();
        $n_appointments_pending = Appointment::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",1)->count();
        //REPORTE GENERAL DE LAS VACUNAS
            // LOS INGRESOS BRUTOS
        $bruto_income_vaccinations = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
            // LA VARIACIÓN PORCENTUAL
        $bruto_income_vaccinations_before = Vaccination::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
            $VPVaccinations = (($bruto_income_vaccinations - $bruto_income_vaccinations_before)/$bruto_income_vaccinations_before)*100;  
            //N° DE VACUNAS PROGRAMADAS
        $n_vaccinations_total = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->count();
        $n_vaccinations_attend = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",3)->count();
        $n_vaccinations_cancel = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",2)->count();
        $n_vaccinations_pending = Vaccination::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",1)->count();

        //REPORTE GENERAL DE LAS CIRUJÍA
            // LOS INGRESOS BRUTOS
            $bruto_income_surgeries = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->sum("amount");
            // LA VARIACIÓN PORCENTUAL
        $bruto_income_surgeries_before = Surgerie::whereYear("created_at",$month_before->format("Y"))->whereMonth("created_at",$month_before->format("m"))->sum("amount");
            $VPsurgeries = (($bruto_income_surgeries - $bruto_income_surgeries_before)/$bruto_income_surgeries_before)*100;  
            //N° DE CIRUJÍA PROGRAMADAS
        $n_surgeries_total = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->count();
        $n_surgeries_attend = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",3)->count();
        $n_surgeries_cancel = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",2)->count();
        $n_surgeries_pending = Surgerie::whereYear("created_at",$year)->whereMonth("created_at",$month)->where("state",1)->count();

        return response()->json([
            "n_surgeries_total" => $n_surgeries_total,
            "n_surgeries_attend" => $n_surgeries_attend,
            "n_surgeries_cancel" => $n_surgeries_cancel,
            "n_surgeries_pending" => $n_surgeries_pending,
            "VPsurgeries" => round($VPsurgeries,2),
            "bruto_income_surgeries" => $bruto_income_surgeries,
            "bruto_income_surgeries_before" => $bruto_income_surgeries_before,
            "n_vaccinations_total" => $n_vaccinations_total,
            "n_vaccinations_attend" => $n_vaccinations_attend,
            "n_vaccinations_cancel" => $n_vaccinations_cancel,
            "n_vaccinations_pending" => $n_vaccinations_pending,
            "VPVaccinations" => round($VPVaccinations,2),
            "bruto_income_vaccinations_before" => $bruto_income_vaccinations_before,
            "bruto_income_vaccinations" => $bruto_income_vaccinations,
            "n_appointments_pending" => $n_appointments_pending,
            "n_appointments_cancel" => $n_appointments_cancel,
            "n_appointments_attend" => $n_appointments_attend,
            "n_appointments_total" => $n_appointments_total,
            "VPAppointments" => round($VPAppointments,2),
            "bruto_income_appoinments" => $bruto_income_appoinments,
            "bruto_income_appoinments_before" => $bruto_income_appoinments_before,
        ]);
    }

    public function kpi_pets_most_payments(Request $request){

        $year = date("Y");
        $month = date("m");

        $month_before = Carbon::parse($year."-".$month."-01")->subMonth();

        // LA MASCOTA CON MAS PAGOS EN LA VETERINARIO
        $pet_payments = collect([]);
            // obtener la mascota con mayor pago a nivel de citas medicas
        $appointments_pet_payment = DB::table("appointment_payments")->where("appointment_payments.deleted_at",NULL)
                                    ->join("appointments","appointment_payments.appointment_id","=","appointments.id")
                                    ->join("pets","appointments.pet_id","=","pets.id")
                                    ->where("appointments.deleted_at",NULL)
                                    ->whereYear("appointment_payments.created_at",$year)
                                    ->whereMonth("appointment_payments.created_at",$month)
                                    ->selectRaw("appointments.pet_id,pets.name, CAST(SUM(appointment_payments.amount) AS DOUBLE PRECISION) as payment_total, count(*) as count_payments")
                                    ->groupBy("appointments.pet_id","pets.name")
                                    ->orderBy("payment_total","desc")
                                    ->first();
        if($appointments_pet_payment){
            $pet_payments->push($appointments_pet_payment);
        }
            // obtener la mascota con mayor pago a nivel de VACUNAS
        $vaccination_pet_payment = DB::table("vaccination_payments")->where("vaccination_payments.deleted_at",NULL)
                                    ->join("vaccinations","vaccination_payments.vaccination_id","=","vaccinations.id")
                                    ->join("pets","vaccinations.pet_id","=","pets.id")
                                    ->where("vaccinations.deleted_at",NULL)
                                    ->whereYear("vaccination_payments.created_at",$year)
                                    ->whereMonth("vaccination_payments.created_at",$month)
                                    ->selectRaw("vaccinations.pet_id,pets.name, CAST(SUM(vaccination_payments.amount) AS DOUBLE PRECISION) as payment_total, count(*) as count_payments")
                                    ->groupBy("vaccinations.pet_id","pets.name")
                                    ->orderBy("payment_total","desc")
                                    ->first();
        if($vaccination_pet_payment){
            $pet_payments->push($vaccination_pet_payment);
        }
            // obtener la mascota con mayor pago a nivel de CIRUJÍA
        $surgerie_pet_payment = DB::table("surgerie_payments")->where("surgerie_payments.deleted_at",NULL)
                                    ->join("surgeries","surgerie_payments.surgerie_id","=","surgeries.id")
                                    ->join("pets","surgeries.pet_id","=","pets.id")
                                    ->where("surgeries.deleted_at",NULL)
                                    ->whereYear("surgerie_payments.created_at",$year)
                                    ->whereMonth("surgerie_payments.created_at",$month)
                                    ->selectRaw("surgeries.pet_id,pets.name, CAST(SUM(surgerie_payments.amount) AS DOUBLE PRECISION) as payment_total, count(*) as count_payments")
                                    ->groupBy("surgeries.pet_id","pets.name")
                                    ->orderBy("payment_total","desc")
                                    ->first();
        if($surgerie_pet_payment){
            $pet_payments->push($surgerie_pet_payment);
        }

        $pets_groups = collect([]);
        foreach ($pet_payments->groupBy("pet_id") as $key => $pet) {
            $pets_groups->push([
                "pet_id" => $key,
                "name" => $pet[0]->name,
                "payment_totals" => $pet->sum("payment_total")
            ]);
        }

        $pet_most_payments = $pets_groups->isNotEmpty() ? $pets_groups->sortByDesc("payment_totals")->first() : NULL;
        $VPPets = 0;$payments_total_before = 0;
        if($pet_most_payments){
            $pet_id = $pet_most_payments["pet_id"];
            $payments_appointment_before = AppointmentPayment::whereYear("created_at",$month_before->format("Y"))
                                            ->whereMonth("created_at",$month_before->format("m"))
                                            ->whereHas("appointment",function($q) use($pet_id){
                                                $q->where("pet_id",$pet_id);
                                            })->sum("amount");

            $payments_vaccination_before = VaccinationPayment::whereYear("created_at",$month_before->format("Y"))
                                            ->whereMonth("created_at",$month_before->format("m"))
                                            ->whereHas("vaccination",function($q) use($pet_id){
                                                $q->where("pet_id",$pet_id);
                                            })->sum("amount");

            $payments_surgerie_before = SurgeriePayment::whereYear("created_at",$month_before->format("Y"))
                                        ->whereMonth("created_at",$month_before->format("m"))
                                        ->whereHas("surgerie",function($q) use($pet_id){
                                            $q->where("pet_id",$pet_id);
                                        })->sum("amount");

            $payments_total_before = $payments_appointment_before + $payments_vaccination_before + $payments_surgerie_before;

            $VPPets = (($pet_most_payments["payment_totals"] - $payments_total_before)/$payments_total_before)*100;
        }
        return response()->json([
            "VPPets" => round($VPPets,2),
            "payments_total_before" => $payments_total_before,
            "payments_vaccination_before" => $payments_vaccination_before,
            "payments_surgerie_before" => $payments_surgerie_before,
            "payments_appointment_before" => $payments_appointment_before,
            "pet_most_payments" => $pet_most_payments,
            "pets_groups" => $pets_groups,
            "surgerie_pet_payment" => $surgerie_pet_payment,
            "vaccination_pet_payment" => $vaccination_pet_payment,
            "appointments_pet_payment" => $appointments_pet_payment,
        ]);
    }

    public function kpi_payments_x_day_month(Request $request){
        $year = $request->year;
        $month = $request->month;

        // PAGOS DE CADA DIA POR EL MES SELECCIONADO
        $payments_for_day_generals = collect([]);
            // PAGOS DE CADA DIA POR EL MES A NIVEL DE CITAS MEDICAS
        $appointment_payments_for_day_month = DB::table("appointment_payments")->where("appointment_payments.deleted_at",NULL)
                                                ->whereYear("appointment_payments.created_at",$year)
                                                ->whereMonth("appointment_payments.created_at",$month)
                                                ->selectRaw("
                                                    TO_CHAR(appointment_payments.created_at, 'YYYY-MM-DD') as created_at_format,
                                                    TO_CHAR(appointment_payments.created_at, 'MM-DD') AS day_created_format,
                                                    CAST(SUM(appointment_payments.amount) AS DOUBLE PRECISION) as total_payments
                                                ")
                                                ->groupBy("created_at_format","day_created_format")
                                                ->orderBy("created_at_format","asc")
                                                ->get();
        foreach ($appointment_payments_for_day_month as $key => $appointment_payment) {
            $payments_for_day_generals->push($appointment_payment);
        }
            // PAGOS DE CADA DIA POR EL MES A NIVEL DE VACUNAS
        $vaccinations_payments_for_day_month = DB::table("vaccination_payments")->where("vaccination_payments.deleted_at",NULL)
                                            ->whereYear("vaccination_payments.created_at",$year)
                                            ->whereMonth("vaccination_payments.created_at",$month)
                                            ->selectRaw("
                                                TO_CHAR(vaccination_payments.created_at, 'YYYY-MM-DD') as created_at_format,
                                                TO_CHAR(vaccination_payments.created_at, 'MM-DD') AS day_created_format,
                                                CAST(SUM(vaccination_payments.amount) AS DOUBLE PRECISION) as total_payments
                                            ")
                                            ->groupBy("created_at_format","day_created_format")
                                            ->orderBy("created_at_format","asc")
                                            ->get();
        foreach ($vaccinations_payments_for_day_month as $key => $vaccination_payment) {
            $payments_for_day_generals->push($vaccination_payment);
        }
                // PAGOS DE CADA DIA POR EL MES A NIVEL DE CIRUJÍA
        $surgeries_payments_for_day_month = DB::table("surgerie_payments")->where("surgerie_payments.deleted_at",NULL)
                                                ->whereYear("surgerie_payments.created_at",$year)
                                                ->whereMonth("surgerie_payments.created_at",$month)
                                                ->selectRaw("
                                                    TO_CHAR(surgerie_payments.created_at, 'YYYY-MM-DD') as created_at_format,
                                                    TO_CHAR(surgerie_payments.created_at, 'MM-DD') AS day_created_format,
                                                    CAST(SUM(surgerie_payments.amount) AS DOUBLE PRECISION) as total_payments
                                                ")
                                                ->groupBy("created_at_format","day_created_format")
                                                ->orderBy("created_at_format","asc")
                                                ->get();
        foreach ($surgeries_payments_for_day_month as $key => $surgeries_payment) {
            $payments_for_day_generals->push($surgeries_payment);
        }

        $payment_for_day_months = collect([]);
        foreach ($payments_for_day_generals->groupBy("created_at_format") as $key => $payment_generals) {
            $payment_for_day_months->push([
                "created_at_format" => $key,
                "day_created_format" => $payment_generals[0]->day_created_format,
                "total_payments" => $payment_generals->sum("total_payments"),
            ]);
        }
        return response()->json([
            "total_payments_month" => $payment_for_day_months->sum("total_payments"),
            "payment_for_day_months" => $payment_for_day_months->sortBy("created_at_format")->values()->all(),
            "payments_for_day_generals" => $payments_for_day_generals,
            "surgeries_payments_for_day_month" => $surgeries_payments_for_day_month,
            "vaccinations_payments_for_day_month" => $vaccinations_payments_for_day_month,
            "appointment_payments_for_day_month" => $appointment_payments_for_day_month,
        ]);
    }

    public function kpi_payments_x_month_of_year(Request $request){

        $year = $request->year;

        $payments_x_month = collect([]);
        // LOS PAGOS POR CADA MES DEL AÑO SELECCIONADO
            // LOS PAGOS POR CADA MES DE LAS CITAS MEDICAS
        $appointment_x_month_of_year = DB::table("appointment_payments")->where("appointment_payments.deleted_at",NULL)
                                        ->whereYear("appointment_payments.created_at",$year)
                                        ->selectRaw("
                                            TO_CHAR(appointment_payments.created_at, 'YYYY-MM') as created_at_format,
                                            CAST(SUM(appointment_payments.amount) AS DOUBLE PRECISION) as total_payments
                                        ")
                                        ->groupBy("created_at_format")
                                        ->orderBy("created_at_format","asc")
                                        ->get();
        foreach ($appointment_x_month_of_year as $key => $appointment_x_month) {
            $payments_x_month->push($appointment_x_month);
        }
            // LOS PAGOS POR CADA MES DE LAS VACUNAS
        $vaccination_x_month_of_year = DB::table("vaccination_payments")->where("vaccination_payments.deleted_at",NULL)
                                        ->whereYear("vaccination_payments.created_at",$year)
                                        ->selectRaw("
                                            TO_CHAR(vaccination_payments.created_at, 'YYYY-MM') as created_at_format,
                                            CAST(SUM(vaccination_payments.amount) AS DOUBLE PRECISION) as total_payments
                                        ")
                                        ->groupBy("created_at_format")
                                        ->orderBy("created_at_format","asc")
                                        ->get();
        foreach ($vaccination_x_month_of_year as $key => $vaccination_x_month) {
            $payments_x_month->push($vaccination_x_month);
        }
            // LOS PAGOS POR CADA MES DE LAS CIRUJÍAS
        $surgerie_x_month_of_year = DB::table("surgerie_payments")->where("surgerie_payments.deleted_at",NULL)
                                        ->whereYear("surgerie_payments.created_at",$year)
                                        ->selectRaw("
                                            TO_CHAR(surgerie_payments.created_at, 'YYYY-MM') as created_at_format,
                                            CAST(SUM(surgerie_payments.amount) AS DOUBLE PRECISION) as total_payments
                                        ")
                                        ->groupBy("created_at_format")
                                        ->orderBy("created_at_format","asc")
                                        ->get();
        foreach ($surgerie_x_month_of_year as $key => $surgerie_x_month) {
            $payments_x_month->push($surgerie_x_month);
        }
        $payment_x_month_of_year = collect([]);
        foreach ($payments_x_month->groupBy("created_at_format") as $key => $payments) {
            $payment_x_month_of_year->push([
                "created_at_format" => $key,
                "total_payments" => $payments->sum("total_payments"),
            ]);
        }

        $payments_x_month_before = collect([]);
        // LOS PAGOS POR CADA MES DEL AÑO ANTERIOR
            // LOS PAGOS POR CADA MES DE LAS CITAS MEDICAS
            $appointment_x_month_of_year = DB::table("appointment_payments")->where("appointment_payments.deleted_at",NULL)
                                            ->whereYear("appointment_payments.created_at",$year - 1)
                                            ->selectRaw("
                                                TO_CHAR(appointment_payments.created_at, 'YYYY-MM') as created_at_format,
                                                CAST(SUM(appointment_payments.amount) AS DOUBLE PRECISION) as total_payments
                                            ")
                                            ->groupBy("created_at_format")
                                            ->orderBy("created_at_format","asc")
                                            ->get();
            foreach ($appointment_x_month_of_year as $key => $appointment_x_month) {
                $payments_x_month_before->push($appointment_x_month);
            }
                // LOS PAGOS POR CADA MES DE LAS VACUNAS
            $vaccination_x_month_of_year = DB::table("vaccination_payments")->where("vaccination_payments.deleted_at",NULL)
                                            ->whereYear("vaccination_payments.created_at",$year - 1)
                                            ->selectRaw("
                                                TO_CHAR(vaccination_payments.created_at, 'YYYY-MM') as created_at_format,
                                                CAST(SUM(vaccination_payments.amount) AS DOUBLE PRECISION) as total_payments
                                            ")
                                            ->groupBy("created_at_format")
                                            ->orderBy("created_at_format","asc")
                                            ->get();
            foreach ($vaccination_x_month_of_year as $key => $vaccination_x_month) {
                $payments_x_month_before->push($vaccination_x_month);
            }
                // LOS PAGOS POR CADA MES DE LAS CIRUJÍAS
            $surgerie_x_month_of_year = DB::table("surgerie_payments")->where("surgerie_payments.deleted_at",NULL)
                                            ->whereYear("surgerie_payments.created_at",$year - 1)
                                            ->selectRaw("
                                                TO_CHAR(surgerie_payments.created_at, 'YYYY-MM') as created_at_format,
                                                CAST(SUM(surgerie_payments.amount) AS DOUBLE PRECISION) as total_payments
                                            ")
                                            ->groupBy("created_at_format")
                                            ->orderBy("created_at_format","asc")
                                            ->get();
            foreach ($surgerie_x_month_of_year as $key => $surgerie_x_month) {
                $payments_x_month_before->push($surgerie_x_month);
            }
            $payment_x_month_of_year_before = collect([]);
            foreach ($payments_x_month_before->groupBy("created_at_format") as $key => $payments) {
                $payment_x_month_of_year_before->push([
                    "created_at_format" => $key,
                    "total_payments" => $payments->sum("total_payments"),
                ]);
            }
        return response()->json([
            "total_payment_before" => $payment_x_month_of_year_before->sum("total_payments"),
            "total_payment_current" =>  $payment_x_month_of_year->sum("total_payments"),
            "payment_x_month_of_year_before" => $payment_x_month_of_year_before,
            "payment_x_month_of_year" => $payment_x_month_of_year,
            "payments_x_month" => $payments_x_month,
            "surgerie_x_month_of_year" => $surgerie_x_month_of_year,
            "vaccination_x_month_of_year" => $vaccination_x_month_of_year,
            "appointment_x_month_of_year" => $appointment_x_month_of_year,
        ]);
    }
}
