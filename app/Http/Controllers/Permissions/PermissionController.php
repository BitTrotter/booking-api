<?php

namespace App\Http\Controllers\Permissions;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    // GET /permissions
    public function index(Request $request)
    {
        $search = $request->get('search');

        $permissions = Permission::query()
            ->where('guard_name', 'api')
            ->when($search, fn ($q) => $q->where('name', 'like', '%' . $search . '%'))
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);

        return response()->json(['permissions' => $permissions]);
    }

    // POST /permissions
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where(fn ($q) => $q->where('guard_name', 'api')),
            ],
        ]);

        $permission = Permission::create([
            'name'       => $validated['name'],
            'guard_name' => 'api',
        ]);

        return response()->json([
            'message'    => 'Permission created successfully',
            'permission' => $permission->only(['id', 'name', 'created_at']),
        ], 201);
    }

    // DELETE /permissions/{id}
    public function destroy(int $id)
    {
        $permission = Permission::where('guard_name', 'api')->findOrFail($id);
        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}
