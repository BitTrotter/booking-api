<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $roles = Role::query()
            ->where('guard_name', 'api')
            ->where('name', 'like', '%' . $search . '%')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'roles' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'created_at' => $role->created_at,
                    'permissions' => $role->permissions->pluck('name'),
                ];
            }),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'api')),
            ],
            'permissions' => 'nullable|array',
        ]);

        $role = DB::transaction(function () use ($validated) {
            $permissions = $this->resolvePermissions($validated['permissions'] ?? []);

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'api',
            ]);

            if (! empty($permissions)) {
                $role->syncPermissions($permissions);
            }

            return $role->load('permissions');
        });

        return response()->json([
            'message' => 'Role created successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'api'))
                    ->ignore($role->id),
            ],
            'permissions' => 'nullable|array',
        ]);

        $role = DB::transaction(function () use ($role, $validated, $request) {
            $permissions = $request->has('permissions')
                ? $this->resolvePermissions($validated['permissions'] ?? [])
                : null;

            if (array_key_exists('name', $validated)) {
                $role->name = $validated['name'];
                $role->save();
            }

            if ($request->has('permissions')) {
                $role->syncPermissions($permissions ?? []);
            }

            return $role->load('permissions');
        });

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'created_at' => $role->created_at,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::query()
            ->where('guard_name', 'api')
            ->find($id);

        if (! $role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully'], 200);
    }

    private function resolvePermissions(array $permissions): array
    {
        $permissions = collect($permissions)
            ->filter(fn ($permission) => $permission !== null && $permission !== '')
            ->values();

        if ($permissions->isEmpty()) {
            return [];
        }

        $resolvedPermissions = Permission::query()
            ->where('guard_name', 'api')
            ->where(function ($query) use ($permissions) {
                $numericPermissions = $permissions
                    ->filter(fn ($permission) => is_numeric($permission))
                    ->map(fn ($permission) => (int) $permission)
                    ->all();

                $stringPermissions = $permissions
                    ->map(fn ($permission) => (string) $permission)
                    ->all();

                if (! empty($numericPermissions)) {
                    $query->orWhereIn('id', $numericPermissions);
                }

                $query->orWhereIn('name', $stringPermissions);
            })
            ->pluck('name');

        if ($resolvedPermissions->count() !== $permissions->count()) {
            throw ValidationException::withMessages([
                'permissions' => ['One or more permissions are invalid for the api guard.'],
            ]);
        }

        return $resolvedPermissions->all();
    }
}
