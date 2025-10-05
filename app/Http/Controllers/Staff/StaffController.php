<?php

namespace App\Http\Controllers\Staff;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\User\UserResource;
use App\Http\Resources\User\UserCollection;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize("viewAny",User::class);
        $search = $request->get("search");

        $users = User::where(DB::raw("users.name || ' ' || COALESCE(users.surname,'') || ' ' || users.email"),"ilike","%".$search."%")
                ->whereHas("roles",function($q) {
                    $q->where("name","not ilike","%veterinario%");
                })        
                ->orderBy("id","desc")->get();

        return response()->json([
            "users" => UserCollection::make($users),
            "roles" => Role::where("name","not ilike","%veterinario%")->get()->map(function($role) {
                return [
                    "id" => $role->id,
                    "name" => $role->name
                ];
            })
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize("create",User::class);
        $is_user_exists = User::where("email",$request->email)->first();
        if($is_user_exists){
            return response()->json([
                "message" => 403,
                "message_text" => "El usuario ya existe"
            ]);
        }
        if($request->hasFile("imagen")){
            $path = Storage::putFile("users",$request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }
        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
        if($request->birthday){
            $request->request->add(["birthday" => $request->birthday." 00:00:00"]);
        }
        $user = User::create($request->all());
        $role = Role::findOrFail($request->role_id);
        $user->assignRole($role);

        return response()->json([
            "message" => 200,
            "user" => UserResource::make($user),
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
        Gate::authorize("update",User::class);
        $is_user_exists = User::where("email",$request->email)->where("id","<>",$id)->first();
        if($is_user_exists){
            return response()->json([
                "message" => 403,
                "message_text" => "El usuario ya existe"
            ]);
        }
        $user = User::findOrFail($id);
        if($request->hasFile("imagen")){
            if($user->avatar){
                Storage::delete($user->avatar);
            }
            $path = Storage::putFile("users",$request->file("imagen"));
            $request->request->add(["avatar" => $path]);
        }
        if($request->password){
            $request->request->add(["password" => bcrypt($request->password)]);
        }
        if($request->birthday){
            $request->request->add(["birthday" => $request->birthday." 00:00:00"]);
        }
        $user->update($request->all());

        if($request->role_id && $request->role_id != $user->role_id){
            $role_old = Role::findOrFail($user->role_id);
            $user->removeRole($role_old);

            $role_new = Role::findOrFail($request->role_id);
            $user->assignRole($role_new);
        }

        return response()->json([
            "message" => 200,
            "user" => UserResource::make($user),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Gate::authorize("delete",User::class);
        $user = User::findOrFail($id);
        if($user->avatar){
            Storage::delete($user->avatar);
        }
        $user->delete();

        return response()->json([
            "message" => 200,
        ]);
    }
}
