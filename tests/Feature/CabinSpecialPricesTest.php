<?php

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PriceCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CabinSpecialPricesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-14'));
    }

    private function cabin(): Cabin
    {
        return Cabin::create([
            'name' => 'Christmas cabin', 'price_per_night' => 1000,
            'capacity' => 4, 'beds' => 2, 'bathrooms' => 1, 'status' => 'available',
        ]);
    }

    private function authenticate(array $permissions = ['show_cabin_details', 'edit_cabin', 'create_reservation']): void
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($user, 'api');
    }

    private function payload(Cabin $cabin): array
    {
        return [
            'cabin_id' => $cabin->id, 'start_date' => '2026-12-24', 'end_date' => '2026-12-27',
            'full_name' => 'Test Guest', 'email' => 'guest@example.com',
            'phone' => '5551234567', 'guest_number' => 1,
        ];
    }

    public function test_admin_can_create_replace_list_and_delete_a_date_price(): void
    {
        $this->authenticate();
        $cabin = $this->cabin();
        $url = '/api/cabins/'.$cabin->id.'/special-prices';
        $this->putJson($url.'/2026-12-25', ['price_per_night' => 1800])
            ->assertCreated()->assertJsonPath('date', '2026-12-25')->assertJsonPath('price_per_night', 1800);
        $this->putJson($url.'/2026-12-25', ['price_per_night' => 1900.25])
            ->assertOk()->assertJsonPath('price_per_night', 1900.25);
        $this->putJson($url.'/2026-12-24', ['price_per_night' => 0])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.date', '2026-12-24')->assertJsonPath('1.price_per_night', 1900.25);
        $this->assertEquals(1000, $cabin->fresh()->price_per_night);
        $this->assertEquals(array_fill_keys(Cabin::WEEKDAYS, 1000), $cabin->fresh()->weekly_prices);
        $this->deleteJson($url.'/2026-12-25')->assertOk();
        $this->deleteJson($url.'/2026-12-25')->assertNotFound();
        $this->assertDatabaseCount('cabin_special_prices', 1);
    }

    public function test_invalid_dates_and_prices_are_rejected(): void
    {
        $this->authenticate();
        $url = '/api/cabins/'.$this->cabin()->id.'/special-prices';
        foreach (['2026-02-30', '2026-2-5', '12-25', 'invalid'] as $date) {
            $this->putJson($url.'/'.$date, ['price_per_night' => 1800])
                ->assertUnprocessable()->assertJsonValidationErrors('date');
            $this->deleteJson($url.'/'.$date)->assertUnprocessable();
        }
        foreach ([-1, 10.123, 1000000, null, 'invalid'] as $price) {
            $this->putJson($url.'/2026-12-25', ['price_per_night' => $price])
                ->assertUnprocessable()->assertJsonValidationErrors('price_per_night');
        }
        $this->putJson($url.'/2026-12-25', [])->assertUnprocessable();
        $this->assertDatabaseCount('cabin_special_prices', 0);
    }

    public function test_routes_require_authentication_and_cabin_permissions(): void
    {
        $url = '/api/cabins/'.$this->cabin()->id.'/special-prices';
        $this->getJson($url)->assertUnauthorized();
        $this->putJson($url.'/2026-12-25', ['price_per_night' => 1800])->assertUnauthorized();
        $this->deleteJson($url.'/2026-12-25')->assertUnauthorized();
        Permission::findOrCreate('show_cabin_details', 'api');
        Permission::findOrCreate('edit_cabin', 'api');
        $this->authenticate([]);
        $this->getJson($url)->assertForbidden();
        $this->putJson($url.'/2026-12-25', ['price_per_night' => 1800])->assertForbidden();
        $this->deleteJson($url.'/2026-12-25')->assertForbidden();
        $this->authenticate(['show_cabin_details']);
        $this->getJson($url)->assertOk();
        $this->putJson($url.'/2026-12-25', ['price_per_night' => 1800])->assertForbidden();
    }

    public function test_exception_only_affects_its_cabin_date_and_year_and_excludes_departure(): void
    {
        $cabin = $this->cabin();
        $cabin->specialPrices()->create(['date' => '2026-12-25', 'price_per_night' => 1800.25]);
        $calculator = new PriceCalculatorService;
        $quote = fn (Cabin $target, string $start, string $end) => $calculator->calculate($target, Carbon::parse($start), Carbon::parse($end));
        $mixed = $quote($cabin, '2026-12-24', '2026-12-27');
        $this->assertEquals(3800.25, $mixed['total']);
        $this->assertEquals([1000, 1800.25, 1000], array_column($mixed['nightly_prices'], 'price'));
        $this->assertNull($mixed['price_per_night']);
        $this->assertEquals(1000, $quote($cabin, '2026-12-24', '2026-12-25')['total']);
        $this->assertEquals(1000, $quote($cabin, '2027-12-25', '2027-12-26')['total']);
        $this->assertEquals(1000, $quote($this->cabin(), '2026-12-25', '2026-12-26')['total']);
        $cabin->specialPrices()->where('date', '2026-12-25')->update(['price_per_night' => 0]);
        $this->assertEquals(0, $quote($cabin, '2026-12-25', '2026-12-26')['total']);
        $cabin->specialPrices()->delete();
        $this->assertEquals(1000, $quote($cabin, '2026-12-25', '2026-12-26')['total']);
    }

    public function test_public_quote_and_reservation_preserve_special_price_after_changes(): void
    {
        $cabin = $this->cabin();
        $cabin->specialPrices()->create(['date' => '2026-12-25', 'price_per_night' => 1800]);
        $payload = $this->payload($cabin);
        $availability = $this->getJson('/api/public/reservations/availability?'.http_build_query($payload))
            ->assertOk()->assertJsonPath('total_price', 3800)->assertJsonPath('nightly_prices.1.price', 1800);
        $response = $this->postJson('/api/public/reservations', $payload)
            ->assertCreated()->assertJsonPath('data.total_price', 3800);
        $reservation = Reservation::firstOrFail();
        $this->assertEquals($availability->json('nightly_prices'), $reservation->nightly_prices);
        $this->authenticate();
        $url = '/api/cabins/'.$cabin->id.'/special-prices/2026-12-25';
        $this->putJson($url, ['price_per_night' => 2500])->assertOk();
        $this->deleteJson($url)->assertOk();
        $reservation->update(['status' => 'confirmed']);
        $this->getJson('/api/public/reservations/'.$reservation->public_code.'/confirmation?token='.$response->json('data.confirmation_token'))
            ->assertOk()->assertJsonPath('data.total_price', 3800)->assertJsonPath('data.nightly_prices.1.price', 1800);
    }

    public function test_admin_quote_single_and_batch_reservations_use_special_totals(): void
    {
        $this->authenticate();
        $cabin = $this->cabin();
        $cabin->specialPrices()->create(['date' => '2026-12-25', 'price_per_night' => 1800]);
        $this->postJson('/api/cabins/'.$cabin->id.'/price', [
            'check_in' => '2026-12-24', 'check_out' => '2026-12-27',
        ])->assertOk()->assertJsonPath('total', 3800);
        $this->postJson('/api/reservations', $this->payload($cabin))
            ->assertCreated()->assertJsonPath('total_price', 3800)->assertJsonPath('nightly_prices.1.price', 1800);

        $first = $this->cabin();
        $second = $this->cabin();
        $first->specialPrices()->create(['date' => '2026-12-25', 'price_per_night' => 1800]);
        $payload = $this->payload($first);
        unset($payload['cabin_id'], $payload['guest_number']);
        $payload['cabins'] = [
            ['cabin_id' => $first->id, 'guest_number' => 1],
            ['cabin_id' => $second->id, 'guest_number' => 1],
        ];
        $payload['group_amount_paid'] = 680;
        $this->postJson('/api/reservations', $payload)->assertCreated()
            ->assertJsonPath('reservations.0.total_price', 3800)->assertJsonPath('reservations.0.amount_paid', '380.00')
            ->assertJsonPath('reservations.1.total_price', 3000)->assertJsonPath('reservations.1.amount_paid', '300.00');
    }

    public function test_deletion_is_scoped_to_cabin_and_cabin_deletion_removes_exceptions(): void
    {
        $this->authenticate();
        $first = $this->cabin();
        $second = $this->cabin();
        $first->specialPrices()->create(['date' => '2026-12-25', 'price_per_night' => 1800]);
        $this->deleteJson('/api/cabins/'.$second->id.'/special-prices/2026-12-25')->assertNotFound();
        $this->assertDatabaseCount('cabin_special_prices', 1);
        $first->delete();
        $this->assertDatabaseCount('cabin_special_prices', 0);
    }
}
