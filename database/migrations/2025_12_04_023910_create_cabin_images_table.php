<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabin_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabin_id')
                  ->constrained('cabins') 
                  ->onDelete('cascade');
            $table->string('url');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            
            // Índice para mejorar búsquedas
            $table->index(['cabin_id', 'is_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabin_images');
    }
};