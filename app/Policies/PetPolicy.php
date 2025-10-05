<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pets\Pet;
use Illuminate\Auth\Access\Response;

class PetPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("list_pet") ? true : false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Pet $pet = null): bool
    {
        return $user->can("profile_pet") ? true : false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can("register_pet") ? true : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pet $pet = null): bool
    {
        return $user->can("edit_pet") ? true : false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pet $pet = null): bool
    {
        return $user->can("delete_pet") ? true : false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Pet $pet): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Pet $pet): bool
    {
        //
    }
}
