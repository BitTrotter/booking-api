<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cabin_id')->constrained()->onDelete('cascade');

            // Fechas de estancia
            $table->date('start_date');
            $table->date('end_date');

            // Precio final
            $table->decimal('total_price', 10, 2);

            // Estado de la reserva
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])
                  ->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
