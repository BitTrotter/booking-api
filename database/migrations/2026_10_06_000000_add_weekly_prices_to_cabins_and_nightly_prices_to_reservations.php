<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabins', function (Blueprint $table) {
            $table->json('weekly_prices')->nullable();
        });

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        DB::table('cabins')->select(['id', 'price_per_night'])->chunkById(100, function ($cabins) use ($days) {
            foreach ($cabins as $cabin) {
                DB::table('cabins')->where('id', $cabin->id)->update([
                    'weekly_prices' => json_encode(array_fill_keys($days, (float) $cabin->price_per_night)),
                ]);
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->json('nightly_prices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('nightly_prices');
        });

        Schema::table('cabins', function (Blueprint $table) {
            $table->dropColumn('weekly_prices');
        });
    }
};
