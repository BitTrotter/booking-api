<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabin_special_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabin_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price_per_night', 8, 2);
            $table->timestamps();
            $table->unique(['cabin_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabin_special_prices');
    }
};
