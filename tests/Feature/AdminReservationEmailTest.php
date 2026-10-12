<?php

namespace Tests\Feature;

use App\Mail\ReservationCreatedMail;
use App\Models\Cabin;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminReservationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $user = User::factory()->create();
        foreach (['create_reservation', 'edit_reservation'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'api'));
        }
        $this->actingAs($user, 'api');
    }

    private function payload(): array
    {
        $cabin = Cabin::create([
            'name' => 'Test cabin', 'price_per_night' => 100,
            'capacity' => 2, 'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ]);

        return [
            'cabin_id' => $cabin->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'full_name' => 'Test Guest', 'phone' => '5551234567', 'guest_number' => 1,
        ];
    }

    public function test_admin_can_create_without_email_and_change_status(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload())
            ->assertCreated()->assertJsonPath('email', null);

        $this->assertNull(Reservation::findOrFail($response->json('id'))->email);
        $this->patchJson('/api/reservations/'.$response->json('id'), ['status' => 'active'])
            ->assertOk()->assertJsonPath('status', 'active');
        Mail::assertNothingSent();
    }

    public function test_admin_can_create_with_null_or_empty_email(): void
    {
        foreach ([null, ''] as $email) {
            $this->postJson('/api/reservations', $this->payload() + ['email' => $email, 'send_email' => false])
                ->assertCreated()->assertJsonPath('email', null);
        }
        Mail::assertNothingSent();
    }

    public function test_admin_does_not_send_when_checkbox_is_false_or_omitted(): void
    {
        foreach ([[], ['send_email' => false]] as $option) {
            $this->postJson('/api/reservations', $this->payload() + ['email' => 'guest@example.com'] + $option)
                ->assertCreated()->assertJsonPath('email', 'guest@example.com');
        }
        Mail::assertNothingSent();
    }

    public function test_admin_sends_to_guest_when_checkbox_is_true(): void
    {
        $response = $this->postJson('/api/reservations', $this->payload() + [
            'email' => 'guest@example.com', 'send_email' => true,
        ])->assertCreated();

        Mail::assertSent(ReservationCreatedMail::class, fn ($mail) =>
            $mail->hasTo('guest@example.com') && $mail->reservation->id === $response->json('id')
        );
        Mail::assertSentCount(1);
    }

    public function test_admin_requires_email_when_sending_is_requested(): void
    {
        foreach ([[], ['email' => null], ['email' => ''], ['email' => 'invalid']] as $contact) {
            $this->postJson('/api/reservations', $this->payload() + ['send_email' => true] + $contact)
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertDatabaseCount('reservations', 0);
        Mail::assertNothingSent();
    }

    public function test_admin_rejects_invalid_email_and_checkbox(): void
    {
        $this->postJson('/api/reservations', $this->payload() + ['email' => 'invalid', 'send_email' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/reservations', $this->payload() + ['send_email' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('send_email');
        Mail::assertNothingSent();
    }

    public function test_public_creation_still_requires_email(): void
    {
        $this->postJson('/api/public/reservations', $this->payload() + ['send_email' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        Mail::assertNothingSent();
    }

    public function test_public_creation_still_sends_even_if_send_email_is_false(): void
    {
        $this->postJson('/api/public/reservations', $this->payload() + [
            'email' => 'guest@example.com', 'send_email' => false,
        ])->assertCreated();

        Mail::assertSent(ReservationCreatedMail::class, fn ($mail) => $mail->hasTo('guest@example.com'));
        Mail::assertSentCount(1);
    }
}
