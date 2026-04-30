<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->onDelete('cascade');

            $table->string('stripe_payment_intent_id')->unique();
            $table->text('stripe_client_secret');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('mxn');

            $table->enum('status', ['pending', 'processing', 'paid', 'failed', 'refunded'])
                  ->default('pending');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
