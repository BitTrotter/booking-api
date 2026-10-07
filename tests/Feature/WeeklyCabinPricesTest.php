<?php

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WeeklyCabinPricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 6)->startOfDay());
    }

    private function weeklyPrices(): array
    {
        return array_replace(array_fill_keys(Cabin::WEEKDAYS, 10), ['friday' => 20, 'saturday' => 20]);
    }

    private function cabin(): Cabin
    {
        return Cabin::create([
            'name' => 'Weekly cabin', 'weekly_prices' => $this->weeklyPrices(),
            'capacity' => 2, 'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ]);
    }

    private function authenticate(): void
    {
        $user = User::factory()->create();
        foreach (['create_cabin', 'edit_cabin', 'show_cabin_details', 'create_reservation'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($user, 'api');
    }

    private function reservationPayload(Cabin $cabin): array
    {
        return [
            'cabin_id' => $cabin->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-10',
            'full_name' => 'Test Guest', 'email' => 'guest@example.com',
            'phone' => '5551234567', 'guest_number' => 1,
        ];
    }

    public function test_cabin_creation_and_update_accept_complete_weekly_prices(): void
    {
        $this->authenticate();
        $response = $this->postJson('/api/cabins', [
            'name' => 'Weekly cabin', 'weekly_prices' => $this->weeklyPrices(),
            'capacity' => 2, 'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ])->assertCreated()->assertJsonPath('weekly_prices.friday', 20);

        $cabin = Cabin::findOrFail($response->json('id'));
        $this->assertEquals(10, $cabin->price_per_night);
        $this->patchJson('/api/cabins/'.$cabin->id, [
            'weekly_prices' => array_replace($this->weeklyPrices(), ['thursday' => 12.50]),
        ])->assertOk()->assertJsonPath('weekly_prices.thursday', 12.5);

        $this->patchJson('/api/cabins/'.$cabin->id, [
            'price_per_night' => 100,
            'weekly_prices' => array_replace($this->weeklyPrices(), ['thursday' => 12.50]),
        ])->assertOk()->assertJsonPath('weekly_prices.friday', 20);
        $this->assertEquals(10, $cabin->fresh()->price_per_night);

        $this->getJson('/api/cabins/'.$cabin->id)->assertOk()->assertJsonMissingPath('price_rules');
        $this->getJson('/api/public/cabins/'.$cabin->id)->assertOk()->assertJsonPath('data.weekly_prices.friday', 20);
        $this->getJson('/api/public/cabins')->assertOk()->assertJsonPath('data.0.weekly_prices.friday', 20);
    }

    public function test_incomplete_negative_unknown_and_overprecise_rates_are_rejected(): void
    {
        $this->authenticate();
        $cabin = $this->cabin();
        $incomplete = $this->weeklyPrices();
        unset($incomplete['sunday']);
        $this->patchJson('/api/cabins/'.$cabin->id, ['weekly_prices' => $incomplete])
            ->assertUnprocessable()->assertJsonValidationErrors('weekly_prices.sunday');

        foreach ([-1, 10.123, 1000000, null] as $invalid) {
            $this->patchJson('/api/cabins/'.$cabin->id, [
                'weekly_prices' => array_replace($this->weeklyPrices(), ['friday' => $invalid]),
            ])->assertUnprocessable()->assertJsonValidationErrors('weekly_prices.friday');
        }
        $this->patchJson('/api/cabins/'.$cabin->id, [
            'weekly_prices' => $this->weeklyPrices() + ['holiday' => 50],
        ])->assertUnprocessable()->assertJsonValidationErrors('weekly_prices');
        $this->assertEquals($this->weeklyPrices(), $cabin->fresh()->weekly_prices);
    }

    public function test_legacy_base_price_initializes_and_updates_all_seven_days(): void
    {
        $this->authenticate();
        $cabin = Cabin::create([
            'name' => 'Legacy cabin', 'price_per_night' => 15,
            'capacity' => 2, 'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ]);
        $this->assertEquals(array_fill_keys(Cabin::WEEKDAYS, 15), $cabin->weekly_prices);
        $this->patchJson('/api/cabins/'.$cabin->id, ['price_per_night' => 25])
            ->assertOk()->assertJsonPath('weekly_prices.friday', 25);
        $this->assertEquals(array_fill_keys(Cabin::WEEKDAYS, 25), $cabin->fresh()->weekly_prices);
    }

    public function test_quote_and_public_reservation_use_the_same_total_and_snapshot(): void
    {
        $cabin = $this->cabin();
        $payload = $this->reservationPayload($cabin);
        $availability = $this->getJson('/api/public/reservations/availability?'.http_build_query($payload))
            ->assertOk()->assertJsonPath('total_price', 30)->assertJsonPath('total_days', 2)
            ->assertJsonPath('nightly_prices.0.price', 10)->assertJsonPath('nightly_prices.1.price', 20);
        $response = $this->postJson('/api/public/reservations', $payload)
            ->assertCreated()->assertJsonPath('data.total_price', 30);

        $reservation = Reservation::firstOrFail();
        $this->assertEquals($availability->json('nightly_prices'), $reservation->nightly_prices);
        $cabin->update(['weekly_prices' => array_fill_keys(Cabin::WEEKDAYS, 100)]);
        $reservation->update(['status' => 'confirmed']);

        $this->getJson('/api/public/reservations/'.$reservation->public_code.'/confirmation?token='.$response->json('data.confirmation_token'))
            ->assertOk()->assertJsonPath('data.total_price', 30)
            ->assertJsonPath('data.price_per_night', null)
            ->assertJsonPath('data.average_price_per_night', 15)
            ->assertJsonPath('data.nightly_prices.1.price', 20);
    }

    public function test_admin_quote_and_reservation_use_weekly_prices(): void
    {
        $this->authenticate();
        $cabin = $this->cabin();
        $this->postJson('/api/cabins/'.$cabin->id.'/price', [
            'check_in' => '2026-10-08', 'check_out' => '2026-10-09',
        ])->assertOk()->assertJsonPath('total', 10)->assertJsonPath('nights', 1);
        $this->postJson('/api/reservations', $this->reservationPayload($cabin))
            ->assertCreated()->assertJsonPath('total_price', 30)
            ->assertJsonPath('nightly_prices.1.price', 20);
        $this->assertEquals(30, Reservation::firstOrFail()->total_price);
    }

    public function test_legacy_confirmation_uses_the_booked_total_after_rates_change(): void
    {
        $cabin = $this->cabin();
        $reservation = Reservation::create([
            'cabin_id' => $cabin->id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-10',
            'total_days' => 2, 'total_price' => 70, 'status' => 'confirmed',
            'confirmation_token' => bcrypt('legacy-token'),
        ]);
        $cabin->update(['weekly_prices' => array_fill_keys(Cabin::WEEKDAYS, 100)]);

        $this->getJson('/api/public/reservations/'.$reservation->public_code.'/confirmation?token=legacy-token')
            ->assertOk()->assertJsonPath('data.total_price', 70)
            ->assertJsonPath('data.price_per_night', 35)
            ->assertJsonPath('data.nightly_prices', null);
    }

    public function test_date_only_inputs_and_retired_routes(): void
    {
        $this->authenticate();
        $cabin = $this->cabin();
        $this->postJson('/api/cabins/'.$cabin->id.'/price', [
            'check_in' => '2026-10-08 10:00', 'check_out' => '2026-10-08 15:00',
        ])->assertUnprocessable();
        $this->getJson('/api/cabins/'.$cabin->id.'/price-rules')->assertNotFound();
        $this->postJson('/api/cabins/'.$cabin->id.'/price-rules', [])->assertNotFound();
        $this->putJson('/api/price-rules/1', [])->assertNotFound();
        $this->deleteJson('/api/price-rules/1')->assertNotFound();
    }

    public function test_migration_initializes_existing_cabins_and_preserves_reservations(): void
    {
        $migration = require database_path('migrations/2026_10_06_000000_add_weekly_prices_to_cabins_and_nightly_prices_to_reservations.php');
        $migration->down();
        $id = DB::table('cabins')->insertGetId([
            'name' => 'Existing cabin', 'price_per_night' => 55.50,
            'capacity' => 2, 'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ]);
        $reservation = Reservation::create([
            'cabin_id' => $id, 'start_date' => '2026-10-08', 'end_date' => '2026-10-10',
            'total_days' => 2, 'total_price' => 150, 'status' => 'confirmed',
        ]);
        $migration->up();
        $this->assertEquals(array_fill_keys(Cabin::WEEKDAYS, 55.50), Cabin::findOrFail($id)->weekly_prices);
        $this->assertEquals(150, $reservation->fresh()->total_price);
        $this->assertNull($reservation->fresh()->nightly_prices);
    }
}
