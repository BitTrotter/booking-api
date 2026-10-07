<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('reservations', function (Blueprint $table) {
                $table->enum('status', ['pending', 'confirmed', 'active', 'cancelled'])->default('pending')->change();
            });

            return;
        }

        // PostgreSQL uses a CHECK constraint for enum columns.
        // Laravel names the constraint as {table}_{column}_check.
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending', 'confirmed', 'active', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE reservations SET status = 'confirmed' WHERE status = 'active'");
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('reservations', function (Blueprint $table) {
                $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled'))");
    }
};
