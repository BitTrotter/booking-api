<?php

namespace App\Http\Controllers\Surgerie;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Exports\DownloadSurgerie;
use App\Models\Surgerie\Surgerie;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Surgerie\SurgeriePayment;
use App\Models\Surgerie\SurgerieSchedule;
use App\Http\Resources\Surgerie\SurgerieResource;
use App\Http\Resources\Surgerie\SurgerieCollection;
use App\Models\Veterinarie\VeterinarieScheduleHour;

class SurgerieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny",Surgerie::class);
        $type_date = $request->type_date;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $state_pay = $request->state_pay;
        $state = $request->state;
        $specie = $request->specie;
        $search_pets = $request->search_pets;
        $search_vets = $request->search_vets;
        $user = auth('api')->user();
        $surgeries = Surgerie::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets,$user)->orderBy("id","desc")->paginate(25);

        return response()->json([
            "total_page" => $surgeries->lastPage(),
            "surgeries" => SurgerieCollection::make($surgeries),
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

        $surgeries = Surgerie::filterMultiple($type_date,$start_date,$end_date,$state_pay,$state,$specie,$search_pets,$search_vets)->orderBy("id","desc")->get();

        return Excel::download(new DownloadSurgerie($surgeries),"listado_de_cirugias_reporte.xlsx");
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize("create",Surgerie::class);
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->surgerie_date)->dayName;
        $surgerie = Surgerie::create([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            "surgerie_date" => $request->surgerie_date,
            "medical_notes" => $request->medical_notes,
            "surgerie_type" => $request->surgerie_type,
            "user_id" => auth('api')->user()->id,
            "amount" => $request->amount,
            "state_pay" => $request->state_pay,
            "outside" => $request->outside,
            "outcome" => $request->outcome,
        ]);

        MedicalRecord::create([
            "veterinarie_id" => $surgerie->veterinarie_id,
            "pet_id"=> $surgerie->pet_id,
            "event_type" => 3,
            "event_date" => $surgerie->surgerie_date,
            "surgerie_id" => $surgerie->id,
            "notes" => $request->outcome,
        ]);
        
        SurgeriePayment::create([
            "surgerie_id" => $surgerie->id,
            "method_payment" => $request->method_payment,
            "amount" => $request->adelanto,
        ]);

        foreach ($request->selected_segment_times as $key => $selected_segment_time) {
            $schedule_hour = VeterinarieScheduleHour::find($selected_segment_time["segment_time_id"]);
            SurgerieSchedule::create([
                "surgerie_id" => $surgerie->id,
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
        Gate::authorize("view",Surgerie::class);
        $surgerie = Surgerie::findOrFail($id);
        return response()->json([
            "surgerie" => SurgerieResource::make($surgerie),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        Gate::authorize("update",Surgerie::class);
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        $dayName = Carbon::parse($request->surgerie_date)->dayName;
        $surgerie = Surgerie::findOrFail($id);
        // $request->amount ->al costo de la cita medica que se quiere editar = 40 
        // $surgerie->payments->sum("amount") -> a los pagos realizados de la cita medica = 50
        if($request->amount < $surgerie->payments->sum("amount")){
            return response()->json([
                "message" => 403,
                "message_text" => "El costo de la cirugia no puede ser menor a lo cancelado (".$surgerie->payments->sum("amount")." PEN)",
            ]);
        }
        $surgerie->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "day" => $dayName,
            // "surgerie_date" => $request->surgerie_date,
            "medical_notes" => $request->medical_notes,
            "amount" => $request->amount,
            "state" => $request->state,
            "outside" => $request->outside,
            "outcome" => $request->outcome,
            "surgerie_type" => $request->surgerie_type,
            // "state_pay" => $request->state_pay
        ]);

        $surgerie->medical_record->update([
            "veterinarie_id" => $request->veterinarie_id,
            "pet_id" => $request->pet_id,
            "notes" => $request->outcome,
        ]);
        if($request->amount == $surgerie->payments->sum("amount")){
            $surgerie->update([
                "state_pay" => 3,
            ]);
        }else{
            $surgerie->update([
                "state_pay" => 2,
            ]);
        }
        if($request->surgerie_date){
            $surgerie->update([
                "surgerie_date" => $request->surgerie_date,
                "reprogramar" => 1,
            ]);
            $surgerie->medical_record->update([
                "event_date" => $request->surgerie_date,
            ]);
        }
        if(sizeof($request->selected_segment_times) > 0){
            foreach ($surgerie->schedules as $key => $schedule) {
                $schedule->delete();
            }
            foreach ($request->selected_segment_times as $key => $selected_segment_time) {
                $schedule_hour = VeterinarieScheduleHour::find($selected_segment_time["segment_time_id"]);
                SurgerieSchedule::create([
                    "surgerie_id" => $surgerie->id,
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
        Gate::authorize("delete",Surgerie::class);
        $surgerie = Surgerie::findOrFail($id);
        if($surgerie->state == 3){
            return response()->json([
                "message" => 403,
            ]);
        }
        $surgerie->medical_record->delete();
        $surgerie->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
