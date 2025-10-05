<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Models\Vaccination\Vaccination;

class VaccinationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can("list_vaccionation") ? true : false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vaccination $vaccination = null): bool
    {
        return $user->can("edit_vaccionation") ? true : false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can("register_vaccionation") ? true : false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vaccination $vaccination = null): bool
    {
        return $user->can("edit_vaccionation") ? true : false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vaccination $vaccination = null): bool
    {
        return $user->can("delete_vaccionation") ? true : false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vaccination $vaccination): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vaccination $vaccination): bool
    {
        //
    }
}
