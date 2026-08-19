<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // A hash is stored; the plaintext value is only returned when a public reservation is created.
            $table->string('confirmation_token')->nullable()->after('status');
        });

        Schema::dropIfExists('checkout_tokens');
    }

    public function down(): void
    {
        Schema::create('checkout_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('confirmation_token');
        });
    }
};
