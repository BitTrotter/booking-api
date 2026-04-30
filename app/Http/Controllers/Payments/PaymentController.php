<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class PaymentController extends Controller
{
    // POST /payments/intent
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $reservation = Reservation::with('cabin')->findOrFail($validated['reservation_id']);

        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Reservation is not in pending state'], 422);
        }

        // Idempotency: return the existing intent if one was already created
        $existing = Payment::where('reservation_id', $reservation->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            return response()->json([
                'client_secret'        => $existing->stripe_client_secret,
                'payment_intent_id'    => $existing->stripe_payment_intent_id,
                'amount'               => $existing->amount,
                'currency'             => $existing->currency,
                'reservation_id'       => $reservation->id,
            ]);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $amountInCents = (int) round($reservation->total_price * 100);
            $currency      = config('services.stripe.currency', 'mxn');

            $intent = PaymentIntent::create([
                'amount'   => $amountInCents,
                'currency' => $currency,
                'metadata' => [
                    'reservation_id' => $reservation->id,
                    'cabin_id'       => $reservation->cabin_id,
                    'user_id'        => $reservation->user_id,
                ],
            ]);

            $payment = Payment::create([
                'reservation_id'            => $reservation->id,
                'stripe_payment_intent_id'  => $intent->id,
                'stripe_client_secret'      => $intent->client_secret,
                'amount'                    => $reservation->total_price,
                'currency'                  => $currency,
                'status'                    => 'pending',
            ]);

            return response()->json([
                'client_secret'     => $intent->client_secret,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount'            => $payment->amount,
                'currency'          => $payment->currency,
                'reservation_id'    => $reservation->id,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Stripe createIntent failed', [
                'message'        => $e->getMessage(),
                'reservation_id' => $reservation->id,
                'user_id'        => $request->user()->id,
            ]);

            $response = ['message' => 'Error creating payment intent'];
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }

    // GET /payments
    public function index()
    {
        $payments = Payment::with('reservation')->latest()->get();

        return response()->json($payments);
    }

    // GET /payments/{reservation_id}
    public function show(Request $request, int $reservationId)
    {
        $reservation = Reservation::findOrFail($reservationId);

        if ($reservation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payment = Payment::where('reservation_id', $reservationId)->latest()->first();

        if (!$payment) {
            return response()->json(['message' => 'No payment found for this reservation'], 404);
        }

        return response()->json([
            'reservation_id'         => $reservation->id,
            'payment_intent_id'      => $payment->stripe_payment_intent_id,
            'amount'                 => $payment->amount,
            'currency'               => $payment->currency,
            'status'                 => $payment->status,
            'paid_at'                => $payment->paid_at,
        ]);
    }

    // POST /payments/webhook  (no auth middleware — called by Stripe)
    public function webhook(Request $request)
    {
        $payload       = $request->getContent();
        $sigHeader     = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid signature'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        match ($event->type) {
            'payment_intent.succeeded'       => $this->handleSucceeded($event->data->object),
            'payment_intent.payment_failed'  => $this->handleFailed($event->data->object),
            default                          => null,
        };

        return response()->json(['received' => true]);
    }

    private function handleSucceeded(object $intent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();

        if (!$payment) {
            Log::warning('Stripe webhook: payment not found for succeeded intent', ['intent_id' => $intent->id]);
            return;
        }

        $payment->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        $payment->reservation()->update(['status' => 'confirmed']);

        Log::info('Payment confirmed', [
            'payment_id'     => $payment->id,
            'reservation_id' => $payment->reservation_id,
        ]);
    }

    private function handleFailed(object $intent): void
    {
        $payment = Payment::where('stripe_payment_intent_id', $intent->id)->first();

        if (!$payment) {
            Log::warning('Stripe webhook: payment not found for failed intent', ['intent_id' => $intent->id]);
            return;
        }

        $payment->update(['status' => 'failed']);

        Log::info('Payment failed', [
            'payment_id'     => $payment->id,
            'reservation_id' => $payment->reservation_id,
        ]);
    }
}
