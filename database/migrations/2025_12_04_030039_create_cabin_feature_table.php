<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabin_feature', function (Blueprint $table) {
            $table->foreignId('cabin_id')
                  ->constrained('cabins')
                  ->onDelete('cascade');
            $table->foreignId('feature_id')
                  ->constrained('features')
                  ->onDelete('cascade');
            $table->timestamps();
            
            // Clave primaria compuesta
            $table->primary(['cabin_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabin_feature');
    }
};