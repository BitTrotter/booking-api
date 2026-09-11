<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignSuperAdminRole extends Command
{
    protected $signature = 'permissions:assign-super-admin {email : Email of an existing user}';

    protected $description = 'Assign the Super Admin role to an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user was found with that email address.');

            return self::FAILURE;
        }

        $role = Role::where('guard_name', 'api')->where('name', 'Super Admin')->first();

        if (! $role) {
            $this->error('The Super Admin role does not exist. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        $user->assignRole($role);

        $this->info("Super Admin role assigned to {$user->email}.");

        return self::SUCCESS;
    }
}
