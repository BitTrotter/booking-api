<?php

namespace App\Http\Controllers\Vaccination;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Exports\DownloadVaccination;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Vaccination\Vaccination;
use App\Models\Vaccination\VaccinationPayment;
use App\Models\Vaccination\VaccinationSchedule;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use App\Http\Resources\Vaccination\VaccinationResource;
use App\Http\Resources\Vaccination\VaccinationCollection;

class VaccinationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny",Vaccination::class);
        $type_date = $request->type_date;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state_pay = $request->state_pay;
        $state = $request->state;
        $specie = $request->specie;
        $search_pets = $request->search_pets;
        $search_vets = $request->search_vets;
        $user = auth('api')->user();
        $vaccinations = Vaccination::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$user)->orderBy("id","desc")->paginate(25);

        return response()->json([
            "total_page" => $vaccinations->lastPage(),
            "vaccinations" => VaccinationCollection::make($vaccinations),
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

        $vaccinations = Vaccination::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets)->orderBy("id","desc")->get();

        return Excel::download(new DownloadVaccination($vaccinations),"listado_de_vacunacion_reporte.xlsx");
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize("create",Vaccination::class);
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->vaccination_date)->dayName;
        $vaccination = Vaccination::create([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            "vaccination_date" => $request->vaccination_date,
            "reason" => $request->reason,
            "user_id" => auth('api')->user()->id,
            "amount" => $request->amount,
            "state_pay" => $request->state_pay,
            "outside" => $request->outside,
            "vaccine_names" => $request->vaccine_names,
            "nex_due_date" => $request->nex_due_date,
        ]);

        MedicalRecord::create([
            "veterinarie_id" => $vaccination->veterinarie_id,
            "pet_id"=> $vaccination->pet_id,
            "event_type" => 2,
            "event_date" => $vaccination->vaccination_date,
            "vaccination_id" => $vaccination->id,
        ]);
        
        VaccinationPayment::create([
            "vaccination_id" => $vaccination->id,
            "method_payment" => $request->method_payment,
            "amount" => $request->adelanto,
        ]);

        foreach ($request->selected_segment_times as $key => $selected_segment_time) {
            $schedule_hour = VeterinarieScheduleHour::find($selected_segment_time["segment_time_id"]);
            VaccinationSchedule::create([
                "vaccination_id" => $vaccination->id,
                "hour" => $schedule_hour->hour,
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
        Gate::authorize("view",Vaccination::class);
        $vaccination = Vaccination::findOrFail($id);
        return response()->json([
            "vaccination" => VaccinationResource::make($vaccination),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize("update",Vaccination::class);
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->date_appointment)->dayName;
        $vaccination = Vaccination::findOrFail($id);
        // $request->amount ->al costo de la cita medica que se quiere editar = 40 
        // $vaccination->payments->sum("amount") -> a los pagos realizados de la cita medica = 50
        if($request->amount < $vaccination->payments->sum("amount")){
            return response()->json([
                "message" => 403,
                "message_text" => "El costo de la cita medica no puede ser menor a lo cancelado (".$vaccination->payments->sum("amount")." PEN)",
            ]);
        }
        $vaccination->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            // "date_appointment" => $request->date_appointment,
            "reason" => $request->reason,
            "amount" => $request->amount,
            "state" => $request->state,
            "outside" => $request->outside,
            "vaccine_names" => $request->vaccine_names,
            "nex_due_date" => $request->nex_due_date,
            // "state_pay" => $request->state_pay
        ]);

        $vaccination->medical_record->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
        ]);
        if($request->amount == $vaccination->payments->sum("amount")){
            $vaccination->update([
                "state_pay" => 3,
            ]);
        }else{
            $vaccination->update([
                "state_pay" => 2,
            ]);
        }
        if($request->vaccination_date){
            $vaccination->update([
                "vaccination_date" => $request->vaccination_date,
                "reprogramar" => 1,
            ]);
            $vaccination->medical_record->update([
                "event_date" => $request->vaccination_date,
            ]);
        }
        if(sizeof($request->selected_segment_times) > 0){
            foreach ($vaccination->schedules as $key => $schedule) {
                $schedule->delete();
            }
            foreach ($request->selected_segment_times as $key => $selected_segment_time) {
                $schedule_hour = VeterinarieScheduleHour::find($selected_segment_time["segment_time_id"]);
                VaccinationSchedule::create([
                    "vaccination_id" => $vaccination->id,
                    "hour" => $schedule_hour->hour,
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
        Gate::authorize("delete",Vaccination::class);
        $vaccination = Vaccination::findOrFail($id);
        if($vaccination->state == 3){
            return response()->json([
                "message" => 403,
            ]);
        }
        $vaccination->medical_record->delete();
        $vaccination->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
