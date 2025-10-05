<?php

namespace App\Http\Controllers\Appointment;

use Carbon\Carbon;
use App\Models\Pets\Pet;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Models\Surgerie\Surgerie;
use Illuminate\Support\Facades\DB;
use App\Exports\DownloadAppointment;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Appointment\Appointment;
use App\Models\Vaccination\Vaccination;
use App\Models\Appointment\AppointmentPayment;
use App\Models\Appointment\AppointmentSchedule;
use App\Models\Veterinarie\VeterinarieScheduleDay;
use App\Models\Veterinarie\VeterinarieScheduleJoin;
use App\Http\Resources\Appointment\AppointmentResource;
use App\Http\Resources\Appointment\AppointmentCollection;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny",Appointment::class);
        $type_date = $request->type_date;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state_pay = $request->state_pay;
        $state = $request->state;
        $specie = $request->specie;
        $search_pets = $request->search_pets;
        $search_vets = $request->search_vets;
        $user = auth('api')->user();
        $appointments = Appointment::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$user)->orderBy("id","desc")->paginate(25);

        return response()->json([
            "total_page" => $appointments->lastPage(),
            "appointments" => AppointmentCollection::make($appointments),
        ]);
    }

    public function filter(Request $request) {
        $date_appointment = $request->date_appointment;
        if(!$date_appointment){
            $date_appointment = $request->vaccination_date;
            if(!$date_appointment){
                $date_appointment = $request->surgerie_date;
            }
        }
        $hour = $request->hour;

        // 1.-Obtener el nombre del dia de la fecha que hemos seleccionado
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($date_appointment)->dayName;
        // 2.- Obtener la lista de veterinarios que atienden ese dia (Schedule_days)
        $veterinarie_days = VeterinarieScheduleDay::where("day","ilike","%".$dayName."%")->orderBy("veterinarie_id","asc")->get();
        // 3.- Obtener los segmentos de tiempos o hora del dia de atención
        $veterinarie_time_availability = collect([]);
        foreach ($veterinarie_days as $key => $veterinarie_day) {
            $segment_time_formats = collect([]);
            $segment_time_joins = VeterinarieScheduleJoin::where("veterinarie_schedule_day_id",$veterinarie_day->id)
                                ->where(function($q) use($hour){
                                    if($hour){
                                        $q->whereHas("veterinarie_schedule_hour",function($subq) use($hour){
                                            $hour_explode = explode(":",$hour);
                                            // ["2","1"]
                                            $subq->where("hour",$hour_explode[0]);
                                        });
                                    }
                                })->get();  
            foreach ($segment_time_joins as $segment_time_join) {
                // LA FECHA DE LA CITA , EL VETERINARIO Y EL SEGMENTO DE TIEMPO O HORA
                $check = Appointment::whereDate("date_appointment",$date_appointment)
                                        ->where("state","<>",2)
                                     ->where("veterinarie_id",$veterinarie_day->veterinarie_id)
                                     ->whereHas("schedules",function ($q) use($segment_time_join){
                                        $q->where("veterinarie_schedule_hour_id",$segment_time_join->veterinarie_schedule_hour_id);
                                     })->first();
                if(!$check){
                    $check = Vaccination::whereDate("vaccination_date",$date_appointment)
                                            ->where("state","<>",2)
                                        ->where("veterinarie_id",$veterinarie_day->veterinarie_id)
                                        ->whereHas("schedules",function ($q) use($segment_time_join){
                                            $q->where("veterinarie_schedule_hour_id",$segment_time_join->veterinarie_schedule_hour_id);
                                        })->first();

                    if(!$check){
                        $check = Surgerie::whereDate("surgerie_date",$date_appointment)
                                            ->where("state","<>",2)
                                        ->where("veterinarie_id",$veterinarie_day->veterinarie_id)
                                        ->whereHas("schedules",function ($q) use($segment_time_join){
                                            $q->where("veterinarie_schedule_hour_id",$segment_time_join->veterinarie_schedule_hour_id);
                                        })->first();
                    }                  
                }
                $segment_time_formats->push([
                    "id" => $segment_time_join->id,
                    "veterinarie_schedule_day_id" => $segment_time_join->veterinarie_schedule_day_id,
                    "veterinarie_schedule_hour_id" => $segment_time_join->veterinarie_schedule_hour_id,
                    "hour" => $segment_time_join->veterinarie_schedule_hour->hour,
                    "schedule_hour" => [
                        "hour_start" => $segment_time_join->veterinarie_schedule_hour->hour_start,
                        "hour_end" => $segment_time_join->veterinarie_schedule_hour->hour_end,
                        "hour" => $segment_time_join->veterinarie_schedule_hour->hour,
                        "hour_start_format" => Carbon::parse(date("Y-m-d").' '.$segment_time_join->veterinarie_schedule_hour->hour_start)->format("h:i A"),//8:00 AM o 9:00 AM 3:00 PM
                        "hour_end_format" => Carbon::parse(date("Y-m-d").' '.$segment_time_join->veterinarie_schedule_hour->hour_end)->format("h:i A"),
                    ],
                    "check" => $check ? true : false,
                ]);
            }
            //4.- Es la agrupación de los segmentos de tiempo por hora
            $segment_time_groups = collect([]);
            foreach ($segment_time_formats->groupBy("hour") as $hourT => $segment_time_format) {
                // Cuenta el numero de segmento que estan disponibles en la lista agrupada
                $count_availability = $segment_time_format->where("check",false)->count();
                $segment_time_groups->push([
                    "hour" => $hourT,
                    "hour_format" => Carbon::parse(date("Y-m-d").' '.$hourT.':00:00')->format("h:i A"),//08:00:00
                    "segment_times" => $segment_time_format,
                    "count_availability" => $count_availability,
                ]);
            }
            if($segment_time_groups->count() != 0){
                $veterinarie_time_availability->push([
                    "id" => $veterinarie_day->veterinarie_id,
                    "full_name" => $veterinarie_day->veterinarie->name.' '.$veterinarie_day->veterinarie->surname,
                    "segment_time_groups"=> $segment_time_groups,
                ]);
            }
        }
        return response()->json([
            "veterinarie_time_availability" => $veterinarie_time_availability,
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

        $appointments = Appointment::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets)->orderBy("id","desc")->get();

        return Excel::download(new DownloadAppointment($appointments),"citas_medicas_reporte.xlsx");
    }

    public function searchPets($search) {
        $pets = Pet::whereHas("owner",function($q) use($search){
            $q->where(DB::raw("pets.name || ' ' || owners.first_name || ' ' || COALESCE(owners.last_name,'') || ' ' || owners.phone || ' ' || owners.n_document"),"ilike","%".$search."%");
        })->get();

        return response()->json([
            "pets" => $pets->map(function($pet) {
                return [
                    "id" => $pet->id,
                    "name" => $pet->name,
                    "specie" => $pet->specie,
                    "breed" => $pet->breed,
                    "owner" => [
                        "id" =>$pet->owner->id,
                        "first_name"  =>$pet->owner->first_name,
                        "last_name"  =>$pet->owner->last_name,
                        "phone"  =>$pet->owner->phone,
                        "n_document"  =>$pet->owner->n_document,
                    ]
                ];
            }),
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize("create",Appointment::class);
        // veterinarie_id
        // pet_id
        // date_appointment
        // reason
        // amount
        // state_pay
        // method_payment
        // adelanto
        // selected_segment_times
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->date_appointment)->dayName;
        $appointment = Appointment::create([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            "date_appointment" => $request->date_appointment,
            "reason" => $request->reason,
            "user_id" => auth('api')->user()->id,
            "amount" => $request->amount,
            "state_pay" => $request->state_pay
        ]);

        MedicalRecord::create([
            "veterinarie_id" => $appointment->veterinarie_id,
            "pet_id"=> $appointment->pet_id,
            "event_type" => 1,
            "event_date" => $appointment->date_appointment,
            "appointment_id" => $appointment->id,
        ]);
        
        AppointmentPayment::create([
            "appointment_id" => $appointment->id,
            "method_payment" => $request->method_payment,
            "amount" => $request->adelanto,
        ]);

        foreach ($request->selected_segment_times as $key => $selected_segment_time) {
            AppointmentSchedule::create([
                "appointment_id" => $appointment->id,
                "veterinarie_schedule_hour_id" => $selected_segment_time["segment_time_id"],
            ]);
        }
        return response()->json([
            "message" => 200,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        Gate::authorize("view",Appointment::class);
        $appointment = Appointment::findOrFail($id);
        return response()->json([
            "appointment" => AppointmentResource::make($appointment),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize("update",Appointment::class);
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->date_appointment)->dayName;
        $appointment = Appointment::findOrFail($id);
        // $request->amount ->al costo de la cita medica que se quiere editar = 40 
        // $appointment->payments->sum("amount") -> a los pagos realizados de la cita medica = 50
        if($request->amount < $appointment->payments->sum("amount")){
            return response()->json([
                "message" => 403,
                "message_text" => "El costo de la cita medica no puede ser menor a lo cancelado (".$appointment->payments->sum("amount")." PEN)",
            ]);
        }
        $appointment->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            // "date_appointment" => $request->date_appointment,
            "reason" => $request->reason,
            "amount" => $request->amount,
            "state" => $request->state,
            // "state_pay" => $request->state_pay
        ]);

        $appointment->medical_record->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
        ]);
        if($request->amount == $appointment->payments->sum("amount")){
            $appointment->update([
                "state_pay" => 3,
            ]);
        }else{
            $appointment->update([
                "state_pay" => 2,
            ]);
        }
        if($request->date_appointment){
            $appointment->update([
                "date_appointment" => $request->date_appointment,
                "reprogramar" => 1,
            ]);
            $appointment->medical_record->update([
                "event_date" => $request->date_appointment,
            ]);
        }
        if(sizeof($request->selected_segment_times) > 0){
            foreach ($appointment->schedules as $key => $schedule) {
                $schedule->delete();
            }
            foreach ($request->selected_segment_times as $key => $selected_segment_time) {
                AppointmentSchedule::create([
                    "appointment_id" => $appointment->id,
                    "veterinarie_schedule_hour_id" => $selected_segment_time["segment_time_id"],
                ]);
            }
        }
        return response()->json([
            "message" => 200
        ]);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize("delete",Appointment::class);
        $appointment = Appointment::findOrFail($id);
        if($appointment->state == 3){
            return response()->json([
                "message" => 403,
            ]);
        }
        $appointment->medical_record->delete();
        $appointment->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
