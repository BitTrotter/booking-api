<?php

namespace App\Http\Controllers\Veterinarie;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Veterinarie\VeterinarieScheduleDay;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use App\Models\Veterinarie\VeterinarieScheduleJoin;
use App\Http\Resources\Veterinarie\VeterinarieResource;
use App\Http\Resources\Veterinarie\VeterinarieCollection;

class VeterinarieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!auth('api')->user()->can("list_veterinary")){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED'
            ],403);
        }
        $search = $request->get("search");

        $veterinaries = User::where(DB::raw("users.name || ' ' || COALESCE(users.surname,'') || ' ' || users.email"),"ilike","%".$search."%")
                ->whereHas("roles",function($q) {
                    $q->where("name","ilike","%veterinario%");
                })
                ->orderBy("id","desc")->get();

        return response()->json([
            "veterinaries" => VeterinarieCollection::make($veterinaries),
        ]);
    }

    public function config() {
        $roles = Role::where("name","ilike","%veterinario%")->get();

        $schedule_hours = VeterinarieScheduleHour::all();

        $schedule_hours_groups = collect([]);

        foreach ($schedule_hours->groupBy("hour") as $key => $schedule_hour) {
            $schedule_hours_groups->push([
                "hour" => $key,
                "hour_format" => Carbon::parse(date("Y-m-d").' '.$key.':00:00')->format("h:i A"),//08:00:00
                "segments_time" => $schedule_hour->map(function($schedule_h) {
                    return [
                        "id" => $schedule_h->id,
                        "hour_start" => $schedule_h->hour_start,
                        "hour_end" => $schedule_h->hour_end,
                        "hour" => $schedule_h->hour,
                        "hour_start_format" => Carbon::parse(date("Y-m-d").' '.$schedule_h->hour_start)->format("h:i A"),//8:00 AM o 9:00 AM 3:00 PM
                        "hour_end_format" => Carbon::parse(date("Y-m-d").' '.$schedule_h->hour_end)->format("h:i A"),
                    ];
                })
            ]);
        }

        return response()->json([
            "roles" => $roles,
            "schedule_hours_groups" => $schedule_hours_groups,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!auth('api')->user()->can("register_veterinary")){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED'
            ],403);
        }
        $is_user_exists = User::where("email",$request->email)->first();
        if($is_user_exists){
            return response()->json([
                "message" => 403,
                "message_text" => "El acceso ya existe"
            ]);
        }

        if($request->hasFile("imagen")){
            $path = Storage::putFile("veterinaries",$request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }
        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
        if($request->birthday){
            $request->request->add(["birthday" => $request->birthday." 00:00:00"]);
        }
        $veterinarie = User::create($request->all());
        $role = Role::findOrFail($request->role_id);
        $veterinarie->assignRole($role);

        $schedule_hour_veterinarie =  collect(json_decode($request->schedule_hour_veterinarie,true));
        foreach ($schedule_hour_veterinarie->groupBy("day") as $key => $schedule_hour_vet) {
            $schedule_day = VeterinarieScheduleDay::create([
                "veterinarie_id" => $veterinarie->id,
                "day" => $key
            ]);
            foreach ($schedule_hour_vet as $key => $schedule_hour) {
                VeterinarieScheduleJoin::create([
                     "veterinarie_schedule_day_id" => $schedule_day->id,
                    "veterinarie_schedule_hour_id" => $schedule_hour["segment_time_id"],
                ]);
            }
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
        if(!auth('api')->user()->can("profile_veterinary")){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED'
            ],403);
        }
        $veterinarie = User::findOrfail($id);
        return response()->json([
            "veterinarie" => VeterinarieResource::make($veterinarie),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        if(!auth('api')->user()->can("edit_veterinary")){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED'
            ],403);
        }
        $is_user_exists = User::where("email",$request->email)->where("id","<>",$id)->first();
        if($is_user_exists){
            return response()->json([
                "message" => 403,
                "message_text" => "El acceso ya existe"
            ]);
        }
        $veterinarie = User::findOrFail($id);
        if($request->hasFile("imagen")){
            if($veterinarie->avatar){
                Storage::delete($veterinarie->avatar);
            }
            $path = Storage::putFile("veterinaries",$request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }
        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
        if($request->birthday){
            $request->request->add(["birthday" => $request->birthday." 00:00:00"]);
        }
        $veterinarie->update($request->all());

        if($request->role_id && $request->role_id != $veterinarie->role_id){
            $role_old = Role::findOrFail($veterinarie->role_id);
            $veterinarie->removeRole($role_old);

            $role_new = Role::findOrFail($request->role_id);
            $veterinarie->assignRole($role_new);
        }

        foreach ($veterinarie->schedule_days as $schedule_day) {
            foreach ($schedule_day->schedule_joins as $schedule_join) {
                $schedule_join->delete();
            }
            $schedule_day->delete();
        }

        $schedule_hour_veterinarie =  collect(json_decode($request->schedule_hour_veterinarie,true));
        foreach ($schedule_hour_veterinarie->groupBy("day") as $key => $schedule_hour_vet) {
            $schedule_day = VeterinarieScheduleDay::create([
                "veterinarie_id" => $veterinarie->id,
                "day" => $key
            ]);
            foreach ($schedule_hour_vet as $key => $schedule_hour) {
                VeterinarieScheduleJoin::create([
                     "veterinarie_schedule_day_id" => $schedule_day->id,
                    "veterinarie_schedule_hour_id" => $schedule_hour["segment_time_id"],
                ]);
            }
        }

        return response()->json([
            "message" => 200,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if(!auth('api')->user()->can("delete_veterinary")){
            return response()->json([
                "message" => 'THIS ACTION IS UNAUTHORIZED'
            ],403);
        }
        $veterinarie = User::findOrFail($id);
        if($veterinarie->avatar){
            Storage::delete($veterinarie->avatar);
        }
        $veterinarie->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
