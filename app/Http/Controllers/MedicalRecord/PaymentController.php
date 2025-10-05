<?php

namespace App\Http\Controllers\MedicalRecord;

use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Surgerie\Surgerie;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DownloadMedicalRecord;
use App\Models\Appointment\Appointment;
use App\Models\Vaccination\Vaccination;
use App\Models\Surgerie\SurgeriePayment;
use App\Models\Appointment\AppointmentPayment;
use App\Models\Vaccination\VaccinationPayment;
use App\Http\Resources\MedicalRecord\Payment\PaymentResource;
use App\Http\Resources\MedicalRecord\Payment\PaymentCollection;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type_date = $request->type_date;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state_pay = $request->state_pay;
        $state = $request->state;
        $specie = $request->specie;
        $search_pets = $request->search_pets;
        $search_vets = $request->search_vets;
        $type_service = $request->type_service;
        $medical_records = MedicalRecord::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$type_service)->orderBy("id","desc")->paginate(25);

        return response()->json([
            "total_page" => $medical_records->lastPage(),
            "medical_records" => PaymentCollection::make($medical_records),
        ]);
    }

    public function downloadExcel(Request $request){

        $type_date = $request->get("type_date");
        $start_date = $request->get("start_date");
        $end_date = $request->get("end_date");
        $state_pay = $request->get("state_pay");
        $state = $request->get("state");
        $specie = $request->get("specie");
        $search_pets = $request->get("search_pets");
        $search_vets = $request->get("search_vets");
        $type_service = $request->get("type_service");

        $medical_records = MedicalRecord::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$type_service)->orderBy("id","desc")->get();

        return Excel::download(new DownloadMedicalRecord($medical_records),"listado_de_registros_medicos_reporte.xlsx");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $method_payment = $request->method_payment;
        $amount = $request->amount;

        if($request->appointment_id){//cita medica
            $appointment = Appointment::findOrFail($request->appointment_id);
            $amount_total_pay = $appointment->payments->sum("amount");//el total cancelado de una cita medica
            // (TOTAL CANCELADO + NUEVO MONTO) > AL COSTO DE LA CITA -> NO VA PODER REGISTRARSE
            if(($amount_total_pay + $amount) > $appointment->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres registrar superar al monto de la deuda (".($appointment->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if(($amount_total_pay + $amount) == $appointment->amount){
                $appointment->update(["state_pay" => 3]);
            }else{
                $appointment->update(["state_pay" => 2]);
            }
            AppointmentPayment::create([
                "appointment_id" => $request->appointment_id,
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }
        if($request->vaccination_id){//vacuna
            $vaccination = Vaccination::findOrFail($request->vaccination_id);
            $amount_total_pay = $vaccination->payments->sum("amount");//el total cancelado de una vacuna
            // (TOTAL CANCELADO + NUEVO MONTO) > AL COSTO DE LA VACUNA -> NO VA PODER REGISTRARSE
            if(($amount_total_pay + $amount) > $vaccination->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres registrar superar al monto de la deuda (".($vaccination->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if(($amount_total_pay + $amount) == $vaccination->amount){
                $vaccination->update(["state_pay" => 3]);
            }else{
                $vaccination->update(["state_pay" => 2]);
            }
            VaccinationPayment::create([
                "vaccination_id" => $request->vaccination_id,
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }
        if($request->surgerie_id){//cirujía
            $surgerie = Surgerie::findOrFail($request->surgerie_id);
            $amount_total_pay = $surgerie->payments->sum("amount");//el total cancelado de una cirujía
            // (TOTAL CANCELADO + NUEVO MONTO) > AL COSTO DE LA CIRUJIA -> NO VA PODER REGISTRARSE
            if(($amount_total_pay + $amount) > $surgerie->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres registrar superar al monto de la deuda (".($surgerie->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if(($amount_total_pay + $amount) == $surgerie->amount){
                $surgerie->update(["state_pay" => 3]);
            }else{
                $surgerie->update(["state_pay" => 2]);
            }
            SurgeriePayment::create([
                "surgerie_id" => $request->surgerie_id,
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }

        $medical_record = MedicalRecord::findOrFail($request->medical_record_id);

        return response()->json([
            "message" => 200,
            "payment" => PaymentResource::make($medical_record)
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $method_payment = $request->method_payment;
        $amount = $request->amount;

        if($request->appointment_id){//cita medica
            $appointment = Appointment::findOrFail($request->appointment_id);
            $amount_total_pay = $appointment->payments->sum("amount");//el total cancelado de una cita medica
            // ((TOTAL CANCELADO - MONYO ACTUAL DEL PAGO) + MONTO A EDITAR) > AL COSTO DE LA CITA -> NO VA PODER REGISTRARSE
            $appointment_payment = AppointmentPayment::findOrFail($id);
            $amount_current = $appointment_payment->amount;
            if((($amount_total_pay - $amount_current) + $amount) > $appointment->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres editar superar al monto de la deuda (".($appointment->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if((($amount_total_pay - $amount_current) + $amount)  == $appointment->amount){
                $appointment->update(["state_pay" => 3]);
            }else{
                $appointment->update(["state_pay" => 2]);
            }
            $appointment_payment->update([
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }
        if($request->vaccination_id){//vacuna
            $vaccination = Vaccination::findOrFail($request->vaccination_id);
            $amount_total_pay = $vaccination->payments->sum("amount");//el total cancelado de una vacuna
            // ((TOTAL CANCELADO - MONYO ACTUAL DEL PAGO) > AL COSTO DE LA VACUNA -> NO VA PODER REGISTRARSE
            $vaccination_payment = VaccinationPayment::findOrFail($id);
            $amount_current = $vaccination_payment->amount;
            if((($amount_total_pay - $amount_current) + $amount) > $vaccination->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres editar superar al monto de la deuda (".($vaccination->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if((($amount_total_pay - $amount_current) + $amount)  == $vaccination->amount){
                $vaccination->update(["state_pay" => 3]);
            }else{
                $vaccination->update(["state_pay" => 2]);
            }
            $vaccination_payment->update([
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }
        if($request->surgerie_id){//cirujía
            $surgerie = Surgerie::findOrFail($request->surgerie_id);
            $amount_total_pay = $surgerie->payments->sum("amount");//el total cancelado de una cirujía
            // ((TOTAL CANCELADO - MONYO ACTUAL DEL PAGO) > AL COSTO DE LA CIRUJIA -> NO VA PODER REGISTRARSE
            $surgerie_payment = SurgeriePayment::findOrFail($id);
            $amount_current = $surgerie_payment->amount;
            if((($amount_total_pay - $amount_current) + $amount) > $surgerie->amount){
                return response()->json([
                    "message" => 403,
                    "message_text" => "El monto del pago que quieres editar superar al monto de la deuda (".($surgerie->amount-$amount_total_pay)." PEN)",
                ]);
            }
            if((($amount_total_pay - $amount_current) + $amount)  == $surgerie->amount){
                $surgerie->update(["state_pay" => 3]);
            }else{
                $surgerie->update(["state_pay" => 2]);
            }
            $surgerie_payment->update([
                "method_payment" => $method_payment,
                "amount" => $amount,
            ]);
        }

        $medical_record = MedicalRecord::findOrFail($request->medical_record_id);

        return response()->json([
            "message" => 200,
            "payment" => PaymentResource::make($medical_record)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request,string $id)
    {
        if($request->get("appointment_id")){//citas medicas
            $appointment_payment = AppointmentPayment::findOrFail($id);
            $appointment_payment->appointment->update(["state_pay" => 2]);
            $appointment_payment->delete();
        }
        if($request->get("vaccination_id")){//vacunas 'null' 
            $vaccination_payment = VaccinationPayment::findOrFail($id);
            $vaccination_payment->vaccination->update(["state_pay" => 2]);
            $vaccination_payment->delete();
        }
        if($request->get("surgerie_id")){//cirujia
            $surgerie_payment = SurgeriePayment::findOrFail($id);
            $surgerie_payment->surgerie->update(["state_pay" => 2]);
            $surgerie_payment->delete();
        }
        $medical_record = MedicalRecord::findOrFail($request->get("medical_record_id"));

        return response()->json([
            "message" => 200,
            "payment" => PaymentResource::make($medical_record)
        ]);
    }
}
