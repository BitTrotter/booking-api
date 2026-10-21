<?php

namespace Tests\Feature;

use App\Mail\AdminReservationCreatedMail;
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
        config(['mail.reservation_admin_emails' => []]);

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

    public function test_site_admin_is_notified_without_guest_email(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);

        $response = $this->postJson('/api/reservations', $this->payload() + ['send_email' => false])
            ->assertCreated();

        Mail::assertSent(AdminReservationCreatedMail::class, fn ($mail) =>
            $mail->hasTo('admin@example.com') && $mail->reservation->id === $response->json('id')
        );
        Mail::assertNotSent(ReservationCreatedMail::class);
        Mail::assertSentCount(1);
    }

    public function test_public_creation_notifies_guest_and_site_admin_separately(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);

        $this->postJson('/api/public/reservations', $this->payload() + ['email' => 'guest@example.com'])
            ->assertCreated();

        Mail::assertSent(ReservationCreatedMail::class, fn ($mail) =>
            $mail->hasTo('guest@example.com') && ! $mail->hasTo('admin@example.com')
            && $mail->cc === [] && $mail->bcc === []
        );
        Mail::assertSent(AdminReservationCreatedMail::class, function ($mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Test Guest', $html);
            $this->assertStringContainsString('guest@example.com', $html);
            $this->assertStringContainsString('5551234567', $html);
            $this->assertStringContainsString('Public website', $html);
            $this->assertStringContainsString('payment has not yet been confirmed', $html);

            return $mail->hasTo('admin@example.com') && $mail->reservation->status === 'pending';
        });
        Mail::assertSentCount(2);
    }

    public function test_panel_creation_sends_both_emails_when_requested(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);

        $this->postJson('/api/reservations', $this->payload() + [
            'email' => 'guest@example.com', 'send_email' => true,
        ])->assertCreated();

        Mail::assertSent(ReservationCreatedMail::class, fn ($mail) => $mail->hasTo('guest@example.com'));
        Mail::assertSent(AdminReservationCreatedMail::class, function ($mail) {
            $this->assertStringContainsString('Admin panel', $mail->render());

            return $mail->hasTo('admin@example.com');
        });
        Mail::assertSentCount(2);
    }

    public function test_batch_creation_notifies_site_admin_for_each_reservation(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);
        $payload = $this->payload();
        $second = $this->payload();
        $payload['cabins'] = [
            ['cabin_id' => $payload['cabin_id'], 'guest_number' => 1],
            ['cabin_id' => $second['cabin_id'], 'guest_number' => 2],
        ];
        unset($payload['cabin_id'], $payload['guest_number']);

        $response = $this->postJson('/api/reservations', $payload)->assertCreated();

        foreach ($response->json('reservations') as $reservation) {
            Mail::assertSent(AdminReservationCreatedMail::class, function ($mail) use ($reservation, $response) {
                if ($mail->reservation->id !== $reservation['id']) {
                    return false;
                }
                $this->assertStringContainsString($response->json('reservation_group_id'), $mail->render());

                return $mail->hasTo('admin@example.com');
            });
        }
        Mail::assertSentCount(2);
    }

    public function test_failed_creation_does_not_notify_site_admin(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);
        $payload = $this->payload() + ['email' => 'guest@example.com'];
        $this->postJson('/api/public/reservations', $payload)->assertCreated();
        Mail::fake();

        $this->postJson('/api/public/reservations', $payload)->assertConflict();
        $payload['email'] = 'invalid';
        $this->postJson('/api/public/reservations', $payload)->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_guest_mail_failure_still_attempts_admin_mail_and_preserves_reservation(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);
        Mail::shouldReceive('to')->once()->with('guest@example.com')
            ->andThrow(new \RuntimeException('Transport unavailable'));
        Mail::shouldReceive('to')->once()->with('admin@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(\Mockery::type(AdminReservationCreatedMail::class));

        $this->postJson('/api/public/reservations', $this->payload() + ['email' => 'guest@example.com'])
            ->assertCreated();

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_admin_mail_failure_does_not_fail_creation(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com']]);
        Mail::shouldReceive('to')->once()->with('admin@example.com')
            ->andThrow(new \RuntimeException('Transport unavailable'));

        $this->postJson('/api/reservations', $this->payload())->assertCreated();

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_multiple_admins_receive_separate_emails_from_both_creation_flows(): void
    {
        $recipients = ['admin@example.com', 'manager@example.com', 'reception@example.com'];
        config(['mail.reservation_admin_emails' => $recipients]);

        foreach (['/api/public/reservations', '/api/reservations'] as $endpoint) {
            Mail::fake();
            $response = $this->postJson($endpoint, $this->payload() + [
                'email' => 'guest@example.com', 'send_email' => true,
            ])->assertCreated();

            foreach ($recipients as $recipient) {
                Mail::assertSent(AdminReservationCreatedMail::class, fn ($mail) =>
                    $mail->hasTo($recipient) && count($mail->to) === 1
                    && $mail->cc === [] && $mail->bcc === []
                    && $mail->reservation->public_code === (
                        $endpoint === '/api/public/reservations'
                            ? $response->json('data.reservation_id')
                            : $response->json('public_code')
                    )
                );
            }
            Mail::assertSent(ReservationCreatedMail::class, fn ($mail) =>
                $mail->hasTo('guest@example.com') && count($mail->to) === 1
                && $mail->cc === [] && $mail->bcc === []
            );
            Mail::assertSentCount(4);
        }
    }

    public function test_failed_admin_delivery_does_not_prevent_remaining_deliveries_in_either_flow(): void
    {
        config(['mail.reservation_admin_emails' => ['admin@example.com', 'manager@example.com']]);
        Mail::shouldReceive('to')->twice()->with('guest@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->twice()->with(\Mockery::type(ReservationCreatedMail::class));
        Mail::shouldReceive('to')->twice()->with('admin@example.com')
            ->andThrow(new \RuntimeException('Transport unavailable'));
        Mail::shouldReceive('to')->twice()->with('manager@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->twice()->with(\Mockery::type(AdminReservationCreatedMail::class));

        foreach (['/api/public/reservations', '/api/reservations'] as $endpoint) {
            $this->postJson($endpoint, $this->payload() + [
                'email' => 'guest@example.com', 'send_email' => true,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('reservations', 2);
    }
}
