<?php

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_reservation_creation_returns_reservation_id(): void
    {
        Cabin::create([
            'name' => 'Cabaña de prueba',
            'description' => 'Cabaña para pruebas',
            'price_per_night' => 1200,
            'capacity' => 2,
            'beds' => 1,
            'bathrooms' => 1,
            'status' => 'available',
        ]);

        $response = $this->postJson('/api/public/reservations', [
            'cabin_id' => 1,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'email' => 'guest@example.com',
            'phone' => '5551234567',
            'guests' => [
                ['full_name' => 'Juan Pérez', 'guest_type' => 'adult'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.reservation_id', 1);
    }

    public function test_checkout_token_can_be_generated_and_used_to_load_reservation(): void
    {
        $cabin = Cabin::create([
            'name' => 'Cabaña de prueba',
            'description' => 'Cabaña para pruebas',
            'price_per_night' => 1200,
            'capacity' => 2,
            'beds' => 1,
            'bathrooms' => 1,
            'status' => 'available',
        ]);

        $reservation = Reservation::create([
            'cabin_id' => $cabin->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'guest_count' => 1,
            'email' => 'guest@example.com',
            'phone' => '5551234567',
            'total_days' => 2,
            'total_price' => 2400,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/public/checkout/token', [
            'reservation_id' => $reservation->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reservation_id', $reservation->id)
            ->assertJsonStructure(['data' => ['checkout_token']]);

        $token = $response->json('data.checkout_token');

        $this->getJson('/api/public/checkout/reservations/' . $token)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $reservation->id);
    }
}
