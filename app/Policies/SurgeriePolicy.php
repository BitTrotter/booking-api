<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Surgerie\Surgerie;
use Illuminate\Auth\Access\Response;

class SurgeriePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("list_surgeries") ? true : false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Surgerie $surgerie = null): bool
    {
        return $user->can("edit_surgeries") ? true : false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can("register_surgeries") ? true : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Surgerie $surgerie = null): bool
    {
        return $user->can("edit_surgeries") ? true : false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Surgerie $surgerie = null): bool
    {
        return $user->can("delete_surgeries") ? true : false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Surgerie $surgerie): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Surgerie $surgerie): bool
    {
        //
    }
}
