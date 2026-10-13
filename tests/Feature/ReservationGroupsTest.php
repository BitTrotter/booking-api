<?php

namespace Tests\Feature;

use App\Mail\ReservationCreatedMail;
use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PriceCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $user = User::factory()->create();
        foreach (['create_reservation', 'edit_reservation', 'list_reservation', 'show_reservation_details'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($user, 'api');
    }

    private function cabin(int $price = 100): Cabin
    {
        return Cabin::create([
            'name' => 'Cabin '.$price, 'price_per_night' => $price,
            'capacity' => 4, 'beds' => 2, 'bathrooms' => 1, 'status' => 'available',
        ]);
    }

    private function contact(): array
    {
        return [
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'full_name' => 'Test Guest', 'phone' => '5551234567',
        ];
    }

    private function single(): array
    {
        return $this->contact() + ['cabin_id' => $this->cabin()->id, 'guest_number' => 1];
    }

    private function batch(): array
    {
        return $this->contact() + ['cabins' => [
            ['cabin_id' => $this->cabin()->id, 'guest_number' => 2, 'amount_paid' => 50],
            ['cabin_id' => $this->cabin(200)->id, 'guest_number' => 3, 'amount_paid' => 80],
            ['cabin_id' => $this->cabin(300)->id, 'guest_number' => 1],
        ]];
    }

    public function test_batch_creates_independent_reservations_with_reciprocal_links(): void
    {
        $payload = $this->batch();
        $response = $this->postJson('/api/reservations', $payload)->assertCreated()
            ->assertJsonCount(3, 'reservations')
            ->assertJsonPath('reservations.0.total_price', 200)
            ->assertJsonPath('reservations.1.total_price', 400)
            ->assertJsonPath('reservations.2.total_price', 600)
            ->assertJsonPath('reservations.0.amount_paid', '50.00')
            ->assertJsonPath('reservations.1.amount_paid', '80.00')
            ->assertJsonPath('reservations.2.amount_paid', null);
        $group = $response->json('reservation_group_id');
        $this->assertTrue(Str::isUuid($group));
        $ids = array_column($response->json('reservations'), 'id');

        foreach ($ids as $id) {
            $detail = $this->getJson('/api/reservations/'.$id)->assertOk()
                ->assertJsonPath('reservation_group_id', $group)
                ->assertJsonCount(2, 'related_reservations')
                ->assertJsonMissingPath('group_reservations');
            $this->assertEqualsCanonicalizing(
                array_values(array_diff($ids, [$id])),
                array_column($detail->json('related_reservations'), 'id')
            );
            $this->assertArrayHasKey('cabin', $detail->json('related_reservations.0'));
            $this->assertArrayNotHasKey('related_reservations', $detail->json('related_reservations.0'));
        }
        $this->getJson('/api/reservations')->assertOk()->assertJsonCount(2, '0.related_reservations');
        foreach ($payload['cabins'] as $item) {
            $this->getJson('/api/public/reservations/availability?'.http_build_query(
                $this->contact() + ['cabin_id' => $item['cabin_id']]
            ))->assertOk()->assertJsonPath('available', false);
        }
        Mail::assertNothingSent();
    }

    public function test_ungrouped_reservations_are_not_related_to_each_other(): void
    {
        foreach ([1, 2] as $unused) {
            $this->postJson('/api/reservations', $this->single())->assertCreated()
                ->assertJsonPath('reservation_group_id', null)
                ->assertJsonPath('related_reservations', []);
        }
        $this->getJson('/api/reservations')->assertOk()
            ->assertJsonPath('0.related_reservations', [])
            ->assertJsonPath('1.related_reservations', []);
    }

    public function test_existing_reservations_can_be_linked_and_unlinked_without_changing_payments(): void
    {
        $first = $this->postJson('/api/reservations', $this->single() + ['amount_paid' => 50])
            ->assertCreated()->json('id');
        $second = $this->postJson('/api/reservations', $this->single())->assertCreated()->json('id');
        $other = $this->postJson('/api/reservations', $this->single() + [
            'reservation_group_id' => (string) Str::uuid(),
        ])->assertCreated()->json('id');
        $group = (string) Str::uuid();
        foreach ([$first, $second] as $id) {
            $this->patchJson('/api/reservations/'.$id, ['reservation_group_id' => strtoupper($group)])
                ->assertOk()->assertJsonPath('reservation_group_id', $group);
        }
        $this->getJson('/api/reservations/'.$first)->assertOk()
            ->assertJsonPath('amount_paid', '50.00')
            ->assertJsonCount(1, 'related_reservations')
            ->assertJsonPath('related_reservations.0.id', $second);
        $this->getJson('/api/reservations/'.$other)->assertOk()->assertJsonCount(0, 'related_reservations');
        $this->patchJson('/api/reservations/'.$second, ['reservation_group_id' => null])
            ->assertOk()->assertJsonPath('related_reservations', []);
        $this->getJson('/api/reservations/'.$first)->assertOk()->assertJsonPath('related_reservations', []);
    }

    public function test_single_creation_can_join_an_existing_group_and_cancellation_is_individual(): void
    {
        $batch = $this->postJson('/api/reservations', $this->batch())->assertCreated();
        $response = $this->postJson('/api/reservations', $this->single() + [
            'reservation_group_id' => $batch->json('reservation_group_id'),
        ])->assertCreated()->assertJsonCount(3, 'related_reservations');
        $this->patchJson('/api/reservations/'.$response->json('id'), ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
        foreach ($batch->json('reservations') as $reservation) {
            $this->assertSame('confirmed', Reservation::findOrFail($reservation['id'])->status);
        }
    }

    public function test_unavailable_or_over_capacity_cabin_prevents_entire_batch(): void
    {
        $payload = $this->batch();
        $occupied = $this->contact() + ['cabin_id' => $payload['cabins'][1]['cabin_id'], 'guest_number' => 1];
        $this->postJson('/api/reservations', $occupied)->assertCreated();
        $this->postJson('/api/reservations', $payload)->assertConflict();
        $this->assertDatabaseCount('reservations', 1);

        $payload = $this->batch();
        $payload['cabins'][1]['guest_number'] = 5;
        $this->postJson('/api/reservations', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('reservations', 1);
        Mail::assertNothingSent();
    }

    public function test_batch_rolls_back_if_a_later_price_calculation_fails(): void
    {
        $payload = $this->batch();
        $this->mock(PriceCalculatorService::class, function ($mock) {
            $mock->shouldReceive('calculate')->once()->ordered()->andReturn([
                'total' => 200, 'nights' => 2, 'nightly_prices' => [],
            ]);
            $mock->shouldReceive('calculate')->once()->ordered()->andThrow(new \RuntimeException('Price error'));
        });
        $this->postJson('/api/reservations', $payload)->assertStatus(500);
        $this->assertDatabaseCount('reservations', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_groups_duplicates_and_ambiguous_batch_fields_are_rejected(): void
    {
        $this->postJson('/api/reservations', $this->single() + ['reservation_group_id' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('reservation_group_id');
        $payload = $this->batch();
        $payload['cabins'][1]['cabin_id'] = $payload['cabins'][0]['cabin_id'];
        $this->postJson('/api/reservations', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('cabins.1.cabin_id');
        foreach (['cabin_id' => $this->cabin()->id, 'guest_number' => 4, 'amount_paid' => 100] as $field => $value) {
            $this->postJson('/api/reservations', $this->batch() + [$field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_batch_sends_one_email_per_reservation_only_when_requested(): void
    {
        $this->postJson('/api/reservations', $this->batch() + [
            'send_email' => true, 'email' => 'guest@example.com',
        ])->assertCreated();
        Mail::assertSent(ReservationCreatedMail::class, 3);
    }

    public function test_public_creation_cannot_join_groups(): void
    {
        $this->postJson('/api/public/reservations', $this->single() + [
            'email' => 'public@example.com', 'reservation_group_id' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertNull(Reservation::firstOrFail()->reservation_group_id);
        $this->postJson('/api/public/reservations', $this->batch() + ['email' => 'public@example.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('cabin_id');
    }

    private function batchWithGroupDeposit(int|float|string $amount): array
    {
        $payload = $this->batch();
        foreach ($payload['cabins'] as $index => $item) {
            unset($payload['cabins'][$index]['amount_paid']);
        }

        return $payload + ['group_amount_paid' => $amount];
    }

    public function test_group_deposit_is_allocated_proportionally_and_persisted(): void
    {
        $response = $this->postJson('/api/reservations', $this->contact() + [
            'group_amount_paid' => 1000,
            'cabins' => [
                ['cabin_id' => $this->cabin(1500)->id, 'guest_number' => 2],
                ['cabin_id' => $this->cabin(1000)->id, 'guest_number' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '600.00')
            ->assertJsonPath('reservations.1.amount_paid', '400.00');

        foreach ($response->json('reservations') as $reservation) {
            $this->assertSame($reservation['amount_paid'], Reservation::findOrFail($reservation['id'])->amount_paid);
        }
    }

    public function test_rounding_preserves_every_cent_and_prefers_the_largest_remainders(): void
    {
        $response = $this->postJson('/api/reservations', $this->batchWithGroupDeposit('1.00'))
            ->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '0.17')
            ->assertJsonPath('reservations.1.amount_paid', '0.33')
            ->assertJsonPath('reservations.2.amount_paid', '0.50');
        $cents = array_map(fn ($reservation) => (int) round((float) $reservation['amount_paid'] * 100), $response->json('reservations'));
        $this->assertSame(100, array_sum($cents));

        $this->postJson('/api/reservations', $this->contact() + [
            'group_amount_paid' => '0.01',
            'cabins' => array_map(fn () => ['cabin_id' => $this->cabin()->id, 'guest_number' => 1], range(1, 3)),
        ])->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '0.01')
            ->assertJsonPath('reservations.1.amount_paid', '0.00')
            ->assertJsonPath('reservations.2.amount_paid', '0.00');
    }

    public function test_zero_and_full_group_deposits_are_supported(): void
    {
        foreach ([0, 1200] as $deposit) {
            $response = $this->postJson('/api/reservations', $this->batchWithGroupDeposit($deposit))->assertCreated();
            foreach ($response->json('reservations') as $reservation) {
                $expected = $deposit === 0 ? 0 : $reservation['total_price'];
                $this->assertEquals($expected, $reservation['amount_paid']);
            }
        }
    }

    public function test_large_amounts_are_allocated_without_integer_overflow(): void
    {
        $this->postJson('/api/reservations', $this->contact() + [
            'group_amount_paid' => '99999999.99',
            'cabins' => [
                ['cabin_id' => $this->cabin(40000000)->id, 'guest_number' => 1],
                ['cabin_id' => $this->cabin(40000000)->id, 'guest_number' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '50000000.00')
            ->assertJsonPath('reservations.1.amount_paid', '49999999.99');
    }

    public function test_free_cabins_receive_no_deposit_and_an_all_free_batch_accepts_only_zero(): void
    {
        $this->postJson('/api/reservations', $this->contact() + [
            'group_amount_paid' => 10,
            'cabins' => [
                ['cabin_id' => $this->cabin(0)->id, 'guest_number' => 1],
                ['cabin_id' => $this->cabin()->id, 'guest_number' => 1],
            ],
        ])->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '0.00')
            ->assertJsonPath('reservations.1.amount_paid', '10.00');

        $payload = $this->contact() + ['cabins' => [
            ['cabin_id' => $this->cabin(0)->id, 'guest_number' => 1],
            ['cabin_id' => $this->cabin(0)->id, 'guest_number' => 1],
        ]];
        $this->postJson('/api/reservations', $payload + ['group_amount_paid' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('group_amount_paid');
        $this->assertDatabaseCount('reservations', 2);
        $this->postJson('/api/reservations', $payload + ['group_amount_paid' => 0])
            ->assertCreated()->assertJsonPath('reservations.0.amount_paid', '0.00');
    }

    public function test_invalid_overpaid_and_mixed_group_deposits_are_rejected_without_creating_reservations(): void
    {
        foreach ([-1, '1.001', 'invalid', 1200.01, 100000000] as $deposit) {
            $this->postJson('/api/reservations', $this->batchWithGroupDeposit($deposit))
                ->assertUnprocessable()->assertJsonValidationErrors('group_amount_paid');
        }
        foreach ([0, 50] as $deposit) {
            $this->postJson('/api/reservations', $this->batch() + ['group_amount_paid' => $deposit])
                ->assertUnprocessable()->assertJsonValidationErrors('cabins.0.amount_paid');
        }
        $this->postJson('/api/reservations', $this->single() + ['group_amount_paid' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('group_amount_paid');
        $this->assertDatabaseCount('reservations', 0);
        Mail::assertNothingSent();
    }

    public function test_deposit_for_a_new_batch_does_not_change_existing_group_members(): void
    {
        $existing = $this->postJson('/api/reservations', $this->single() + [
            'reservation_group_id' => (string) Str::uuid(), 'amount_paid' => 50,
        ])->assertCreated();
        $payload = $this->batchWithGroupDeposit(120) + ['reservation_group_id' => $existing->json('reservation_group_id')];
        $this->postJson('/api/reservations', $payload)->assertCreated()
            ->assertJsonPath('reservations.0.amount_paid', '20.00')
            ->assertJsonPath('reservations.1.amount_paid', '40.00')
            ->assertJsonPath('reservations.2.amount_paid', '60.00');
        $this->assertSame('50.00', Reservation::findOrFail($existing->json('id'))->amount_paid);
        $this->patchJson('/api/reservations/'.$existing->json('id'), ['group_amount_paid' => 100])
            ->assertUnprocessable()->assertJsonValidationErrors('group_amount_paid');
    }
}
