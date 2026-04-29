<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;


class UsersController extends Controller
{

    public function index(Request $request)
    {
        $search = $request->get('search');

        $users = User::with('roles')->where('name', 'like', '%' . $search . '%')
            ->orWhere('email', 'like', '%' . $search . '%')
            ->orderBy("id", "desc")
            ->get();

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ];
            })
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'username' => 'nullable|string|max:255|unique:users,username',
            'last_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'roles' => 'nullable|array',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $roles = $this->resolveRoles($validated['roles'] ?? []);

            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
            ]);

            if (! empty($roles)) {
                $user->syncRoles($roles);
            }

            return $user->load('roles');
        });

        return response()->json([
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'last_name' => $user->last_name,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'roles' => $user->roles()->pluck('name'),
            ]
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'username' => 'nullable|string|max:255|unique:users,username,' . $user->id,
            'last_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
            'roles' => 'nullable|array',
        ]);

        $user = DB::transaction(function () use ($user, $validated, $request) {
            $roles = $request->has('roles')
                ? $this->resolveRoles($validated['roles'] ?? [])
                : null;

            $user->fill([
                'name' => $validated['name'] ?? $user->name,
                'email' => $validated['email'] ?? $user->email,
                'username' => $validated['username'] ?? $user->username,
                'last_name' => $validated['last_name'] ?? $user->last_name,
                'status' => $validated['status'] ?? $user->status,
            ]);

            if (! empty($validated['password'])) {
                $user->password = bcrypt($validated['password']);
            }

            $user->save();

            if ($request->has('roles')) {
                $user->syncRoles($roles ?? []);
            }

            return $user->load('roles');
        });

        return response()->json([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'last_name' => $user->last_name,
                'status' => $user->status,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    private function resolveRoles(array $roles): array
    {
        $roles = collect($roles)
            ->filter(fn ($role) => $role !== null && $role !== '')
            ->values();

        if ($roles->isEmpty()) {
            return [];
        }

        $resolvedRoles = Role::query()
            ->where('guard_name', 'api')
            ->where(function ($query) use ($roles) {
                $numericRoles = $roles->filter(fn ($role) => is_numeric($role))->map(fn ($role) => (int) $role)->all();
                $stringRoles = $roles->map(fn ($role) => (string) $role)->all();

                if (! empty($numericRoles)) {
                    $query->orWhereIn('id', $numericRoles);
                }

                $query->orWhereIn('name', $stringRoles);
            })
            ->pluck('name');

        if ($resolvedRoles->count() !== $roles->count()) {
            throw ValidationException::withMessages([
                'roles' => ['One or more roles are invalid for the api guard.'],
            ]);
        }

        return $resolvedRoles->all();
    }
}
