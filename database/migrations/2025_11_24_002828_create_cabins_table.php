<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::create('cabins', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price_per_night', 8, 2);
        $table->integer('capacity')->default(1);
        $table->integer('beds')->default(1);
        $table->integer('bathrooms')->default(1);
        $table->json('services')->nullable(); 
        $table->enum('status', ['available', 'maintenance'])->default('available');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabins');
    }
};
