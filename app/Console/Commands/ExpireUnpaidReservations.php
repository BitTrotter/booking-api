<?php

namespace App\Console\Commands;

use App\Mail\ReservationStatusChangedMail;
use App\Models\Reservation;
use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class ExpireUnpaidReservations extends Command
{
    protected $signature = 'reservations:expire-unpaid';

    protected $description = 'Cancel pending reservations that have not been paid within five minutes';

    public function __construct(private MailService $mail)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cancelled = 0;

        $expired = Reservation::query()
            ->with('payment')
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->get();

        foreach ($expired as $reservation) {
            // Stripe is cancelled first so a client cannot complete its existing intent
            // after the reservation has been released.
            $this->cancelStripeIntent($reservation);

            // A webhook may have confirmed it while the command was running. The
            // conditional update makes this transition atomic.
            $wasCancelled = Reservation::query()
                ->whereKey($reservation->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            if (! $wasCancelled) {
                continue;
            }

            $reservation->status = 'cancelled';
            $cancelled++;
            $this->mail->send(
                $reservation->email,
                new ReservationStatusChangedMail($reservation, 'pending')
            );
        }

        $this->info("Cancelled {$cancelled} unpaid reservation(s).");

        return self::SUCCESS;
    }

    private function cancelStripeIntent(Reservation $reservation): void
    {
        $payment = $reservation->payment;

        if (! $payment || ! in_array($payment->status, ['pending', 'processing'], true)) {
            return;
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            PaymentIntent::retrieve($payment->stripe_payment_intent_id)->cancel();
        } catch (\Throwable $exception) {
            // The reservation is still cancelled below. A late successful payment is
            // handled safely by the webhook and recorded for manual follow-up.
            Log::warning('Could not cancel expired Stripe payment intent', [
                'reservation_id' => $reservation->id,
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
