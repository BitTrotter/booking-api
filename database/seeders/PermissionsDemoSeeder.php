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
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // create permissions
        Permission::create(['guard_name' => 'api','name' => 'register_rol']);
        Permission::create(['guard_name' => 'api','name' => 'list_rol']);
        Permission::create(['guard_name' => 'api','name' => 'edit_rol']);
        Permission::create(['guard_name' => 'api','name' => 'delete_rol']);

        Permission::create(['guard_name' => 'api','name' => 'register_veterinary']);
        Permission::create(['guard_name' => 'api','name' => 'list_veterinary']);
        Permission::create(['guard_name' => 'api','name' => 'edit_veterinary']);
        Permission::create(['guard_name' => 'api','name' => 'delete_veterinary']);
        Permission::create(['guard_name' => 'api','name' => 'profile_veterinary']);

        Permission::create(['guard_name' => 'api','name' => 'register_pet']);
        Permission::create(['guard_name' => 'api','name' => 'list_pet']);
        Permission::create(['guard_name' => 'api','name' => 'edit_pet']);
        Permission::create(['guard_name' => 'api','name' => 'delete_pet']);
        Permission::create(['guard_name' => 'api','name' => 'profile_pet']);

        Permission::create(['guard_name' => 'api','name' => 'register_staff']);
        Permission::create(['guard_name' => 'api','name' => 'list_staff']);
        Permission::create(['guard_name' => 'api','name' => 'edit_staff']);
        Permission::create(['guard_name' => 'api','name' => 'delete_staff']);

        Permission::create(['guard_name' => 'api','name' => 'register_appointment']);
        Permission::create(['guard_name' => 'api','name' => 'list_appointment']);
        Permission::create(['guard_name' => 'api','name' => 'edit_appointment']);
        Permission::create(['guard_name' => 'api','name' => 'delete_appointment']);

        Permission::create(['guard_name' => 'api','name' => 'show_payment']);
        Permission::create(['guard_name' => 'api','name' => 'edit_payment']);

        Permission::create(['guard_name' => 'api','name' => 'calendar']);

        Permission::create(['guard_name' => 'api','name' => 'register_vaccionation']);
        Permission::create(['guard_name' => 'api','name' => 'list_vaccionation']);
        Permission::create(['guard_name' => 'api','name' => 'edit_vaccionation']);
        Permission::create(['guard_name' => 'api','name' => 'delete_vaccionation']);

        Permission::create(['guard_name' => 'api','name' => 'register_surgeries']);
        Permission::create(['guard_name' => 'api','name' => 'list_surgeries']);
        Permission::create(['guard_name' => 'api','name' => 'edit_surgeries']);
        Permission::create(['guard_name' => 'api','name' => 'delete_surgeries']);

        Permission::create(['guard_name' => 'api','name' => 'show_medical_records']);

        Permission::create(['guard_name' => 'api','name' => 'show_report_grafics']);
        // create roles and assign existing permissions

        $role3 = Role::create(['guard_name' => 'api','name' => 'Super-Admin']);
        // gets all permissions via Gate::before rule; see AuthServiceProvider

        $user = \App\Models\User::factory()->create([
            'name' => 'Laravest Code',
            'email' => 'laravest@gmail.com',
            'password' => bcrypt('12345678')
        ]);
        $user->assignRole($role3);
    }
}