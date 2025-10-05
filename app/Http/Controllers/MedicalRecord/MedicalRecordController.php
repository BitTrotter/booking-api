<?php

namespace App\Http\Controllers\MedicalRecord;

use Carbon\Carbon;
use App\Models\Pets\Pet;
use Illuminate\Http\Request;
use App\Models\MedicalRecord;
use App\Http\Controllers\Controller;
use App\Models\Appointment\Appointment;
use App\Http\Resources\Pets\PetResource;
use App\Http\Resources\MedicalRecord\MedicalRecordPetCollection;
use App\Http\Resources\MedicalRecord\Calendar\MedicalRecordCalendarResource;
use App\Http\Resources\MedicalRecord\Calendar\MedicalRecordCalendarCollection;

class MedicalRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $pet_id = $request->pet_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $pet = Pet::findOrFail($pet_id);

        $medical_records = MedicalRecord::where("pet_id",$pet_id)->where(function($q) use($start_date,$end_date){
            if($start_date && $end_date){
                $q->whereBetween("event_date",[Carbon::parse($start_date)->format("Y-m-d")." 00:00:00",Carbon::parse($end_date)->format("Y-m-d")." 23:59:59"]);
            }
        })->orderBy("id","desc")->get();
        return response()->json([
            "pet" => PetResource::make($pet),
            "historial_records" => MedicalRecordPetCollection::make($medical_records),
        ]);
    }

    public function calendar(Request $request){

        $user = auth('api')->user();
        $medical_records = MedicalRecord::where(function($q) use($user){
            if($user && strpos(strtolower($user->role->name),'veterinario') !== false){
                $q->where("veterinarie_id",$user->id);
            }
        })->orderBy("id","desc")->whereYear("created_at",date('Y'))->whereMonth("created_at",">=",date('m'))->get();

        return response()->json([
            "calendars" => MedicalRecordCalendarCollection::make($medical_records),
        ]);
    }
    public function update_aux(Request $request, string $id)
    {
        $medical_record = MedicalRecord::findOrFail($id);
        if($medical_record->appointment_id){
            $medical_record->appointment->update([
                "state" => $request->state,
            ]);
        }
        if($medical_record->vaccination_id){
            $medical_record->vaccination->update([
                "state" => $request->state,
            ]);
        }
        if($medical_record->surgerie_id){
            $medical_record->surgerie->update([
                "state" => $request->state,
                "outcome" => $request->notes,
            ]);
        }
        $medical_record->update([
            "notes" => $request->notes,
        ]);
        return response()->json([
            "event" => MedicalRecordCalendarResource::make($medical_record),
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
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
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
