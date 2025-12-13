<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
      Schema::create('cabin_price_rules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cabin_id')->constrained()->cascadeOnDelete();

    $table->enum('type', [
        'weekend',
        'weekday',
        'date_range',
        'min_nights',
        'custom'
    ]);

    $table->decimal('price_per_night', 8, 2);

    $table->json('days')->nullable();        // ["friday","saturday","sunday"]
    $table->date('start_date')->nullable();
    $table->date('end_date')->nullable();
    $table->integer('min_nights')->nullable();

    $table->boolean('active')->default(true);
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_price_rules');
    }
};
