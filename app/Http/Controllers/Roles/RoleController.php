<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        
        $search = $request->get('search');
        $roles = Role::where('name', 'like', '%' . $search . '%')->orderBy("id","desc")->get();
        return response()->json([
            'roles' => $roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at,
                    'permissions' => $role->permissions()->pluck('name'),
                ];
            })
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $existRole = Role::where('name',$request->name)->first();
        if($existRole){
            return response()->json(['message' => 'Role already exists'], 400);            
        }
        $role = Role::create(['name' => $request->name, 'guard_name' => 'api']);
        if($request->permissions && is_array($request->permissions)){
            $role->syncPermissions($request->permissions);
        }
        return response()->json(['message' => 'Role created successfully', 'role' => $role], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::findById($id, 'api');
        if(!$role){
            return response()->json(['message' => 'Role not found'], 404);            
        }
        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'permissions' => $role->permissions()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::findById($id, 'api');
        if(!$role){
            return response()->json(['message' => 'Role not found'], 404);            
        }
        $role->name = $request->name;
        $role->save();
        if($request->permissions && is_array($request->permissions)){
            $role->syncPermissions($request->permissions);
        }
        return response()->json(['message' => 'Role updated successfully', 'role' => $role], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findById($id, 'api');
        if(!$role){
            return response()->json(['message' => 'Role not found'], 404);            
        }
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully'], 200);
    }
}
