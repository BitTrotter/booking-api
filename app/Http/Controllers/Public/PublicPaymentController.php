<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PublicPaymentController extends Controller
{
    // POST /api/public/payments/intent
    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
        ]);

        $reservation = Reservation::with('cabin')->findOrFail($validated['reservation_id']);

        // Only public (guest) reservations can use this endpoint
        if ($reservation->user_id !== null) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Reservation is not in pending state'], 422);
        }

        // Idempotency: return existing intent if one was already created
        $existing = Payment::where('reservation_id', $reservation->id)
            ->whereIn('status', ['pending', 'processing'])
            ->first();

        if ($existing) {
            return response()->json([
                'client_secret'     => $existing->stripe_client_secret,
                'payment_intent_id' => $existing->stripe_payment_intent_id,
                'amount'            => $existing->amount,
                'currency'          => $existing->currency,
                'reservation_id'    => $reservation->id,
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
                ],
            ]);

            $payment = Payment::create([
                'reservation_id'           => $reservation->id,
                'stripe_payment_intent_id' => $intent->id,
                'stripe_client_secret'     => $intent->client_secret,
                'amount'                   => $reservation->total_price,
                'currency'                 => $currency,
                'status'                   => 'pending',
            ]);

            return response()->json([
                'client_secret'     => $intent->client_secret,
                'payment_intent_id' => $payment->stripe_payment_intent_id,
                'amount'            => $payment->amount,
                'currency'          => $payment->currency,
                'reservation_id'    => $reservation->id,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Public Stripe createIntent failed', [
                'message'        => $e->getMessage(),
                'reservation_id' => $reservation->id,
            ]);

            $response = ['message' => 'Error creating payment intent'];
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }
}
