<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsDemoSeeder extends Seeder
{
    /**
     * Create the initial roles and permissions.
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // --------------------------
        // Dashboard
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'show_dashboard_reports']);

        // --------------------------
        // Roles & Permissions
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'create_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_role']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_role']);

        // --------------------------
        // Staff
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'create_staff']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_staff']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_staff']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_staff']);

        // --------------------------
        // Cabins
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'create_cabin']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_cabin']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_cabin']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_cabin']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_cabin_details']);

        // --------------------------
        // Reservations
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'create_reservation']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_reservation']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_reservation']);
        Permission::create(['guard_name' => 'api', 'name' => 'cancel_reservation']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_reservation_details']);

        // --------------------------
        // Guests
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'create_guest']);
        Permission::create(['guard_name' => 'api', 'name' => 'list_guest']);
        Permission::create(['guard_name' => 'api', 'name' => 'edit_guest']);
        Permission::create(['guard_name' => 'api', 'name' => 'delete_guest']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_guest_profile']);

        // --------------------------
        // Payments
        // --------------------------
        Permission::create(['guard_name' => 'api', 'name' => 'list_payment']);
        Permission::create(['guard_name' => 'api', 'name' => 'refund_payment']);
        Permission::create(['guard_name' => 'api', 'name' => 'show_payment_details']);

        // Example role: Super Admin
        $role = Role::create(['guard_name' => 'api', 'name' => 'Super Admin']);
        $role->givePermissionTo(Permission::all());
    }
}
