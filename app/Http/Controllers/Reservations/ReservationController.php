<?php

namespace App\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Mail\ReservationCreatedMail;
use App\Mail\ReservationStatusChangedMail;
use App\Models\Cabin;
use App\Models\Reservation;
use App\Services\MailService;
use App\Services\PriceCalculatorService;
use Brick\Math\BigInteger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function __construct(private MailService $mail) {}
    // GET /reservations
    public function index()
    {
        $reservations = Reservation::with(['cabin', 'user', 'payment'])->get();
        $this->includeRelatedReservations($reservations);
        return response()->json($reservations, 200);
    }

    // GET /reservations/{id}
    public function show($id)
    {
        $reservation = Reservation::with(['cabin', 'user', 'payment'])->findOrFail($id);
        $this->includeRelatedReservations(new Collection([$reservation]));
        return response()->json($reservation, 200);
    }

    // POST /reservations
    public function store(Request $request, PriceCalculatorService $priceCalculator)
    {
        try {
            $validated = $request->validate([
                'cabin_id' => 'required_without:cabins|prohibits:cabins|integer|exists:cabins,id',
                'cabins' => 'required_without:cabin_id|prohibits:cabin_id|array|list|min:2',
                'cabins.*' => 'required|array:cabin_id,guest_number,amount_paid',
                'cabins.*.cabin_id' => 'required|integer|distinct|exists:cabins,id',
                'cabins.*.guest_number' => 'required|integer|min:1',
                'cabins.*.amount_paid' => 'nullable|numeric|min:0',
                'reservation_group_id' => 'nullable|uuid',
                'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
                'end_date' => 'required|date_format:Y-m-d|after:start_date',
                'full_name' => 'required|string|max:150',
                'email'  => 'nullable|required_if:send_email,true|email|max:255',
                'send_email' => 'sometimes|boolean',
                'phone'  => 'required|string|max:20',
                'guest_number' => 'required_without:cabins|prohibits:cabins|integer|min:1',
                'payment_method' => 'nullable|in:stripe,cash,bank_transfer,terminal,courtesy',
                'payment_reference' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'amount_paid' => 'nullable|prohibits:cabins|numeric|min:0',
                'group_amount_paid' => 'nullable|prohibits:cabin_id|numeric|decimal:0,2|min:0|max:99999999.99',
            ]);

            if (isset($validated['group_amount_paid'])) {
                foreach ($validated['cabins'] as $index => $item) {
                    if (isset($item['amount_paid'])) {
                        throw ValidationException::withMessages([
                            "cabins.$index.amount_paid" => 'Use either a group deposit or individual deposits, not both.',
                        ]);
                    }
                }
            }

            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $isBatch = isset($validated['cabins']);
            $reservations = DB::transaction(function () use ($validated, $user, $priceCalculator, $isBatch) {
                $startDate = Carbon::parse($validated['start_date'])->toDateString();
                $endDate = Carbon::parse($validated['end_date'])->toDateString();

                $items = $isBatch ? $validated['cabins'] : [[
                    'cabin_id' => $validated['cabin_id'],
                    'guest_number' => $validated['guest_number'],
                    'amount_paid' => $validated['amount_paid'] ?? null,
                ]];
                $groupId = $validated['reservation_group_id'] ?? ($isBatch ? (string) Str::uuid() : null);
                $groupId = $groupId === null ? null : strtolower($groupId);
                // Use the same cabin locks as public creation, in a consistent order.
                $cabins = Cabin::whereIn('id', array_column($items, 'cabin_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                // Solape real: existing.start < new.end AND existing.end > new.start
                $existing = Reservation::whereIn('cabin_id', array_column($items, 'cabin_id'))
                    ->whereIn('status', ['pending', 'confirmed', 'active'])
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<', $endDate)
                            ->where('end_date', '>', $startDate);
                    })
                    ->exists();

                if ($existing) {
                    return null;
                }

                // Validate every cabin before inserting any reservation.
                foreach ($items as $item) {
                    if ($item['guest_number'] > $cabins->get($item['cabin_id'])->capacity) {
                        return false;
                    }
                }

                $prices = [];
                foreach ($items as $item) {
                    $prices[] = $priceCalculator->calculate(
                        $cabins->get($item['cabin_id']),
                        Carbon::parse($startDate),
                        Carbon::parse($endDate)
                    );
                }

                if (isset($validated['group_amount_paid'])) {
                    $allocations = $this->allocateGroupDeposit($validated['group_amount_paid'], $prices);
                    foreach ($items as $index => $item) {
                        $items[$index]['amount_paid'] = $allocations[$index];
                    }
                }

                $reservations = new Collection;
                foreach ($items as $index => $item) {
                    $price = $prices[$index];
                    $reservation = Reservation::create([
                        'user_id'    => $user->id,
                        'created_by' => $user->id,
                        'cabin_id'   => $item['cabin_id'],
                        'reservation_group_id' => $groupId,
                        'start_date' => $startDate,
                        'end_date'   => $endDate,
                        'guest_count' => $item['guest_number'],
                        'full_name'   => $validated['full_name'],
                        'email'      => $validated['email'] ?? null,
                        'phone'      => $validated['phone'],
                        'total_days' => $price['nights'],
                        'total_price' => $price['total'],
                        'nightly_prices' => $price['nightly_prices'],
                        'status'     => 'confirmed',
                        'payment_method' => $validated['payment_method'] ?? null,
                        'payment_reference' => $validated['payment_reference'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                        'amount_paid' => $item['amount_paid'] ?? null,
                    ]);
                    $reservations->push($reservation);
                }

                return $reservations->load(['cabin', 'user', 'payment']);
            });

            if ($reservations === false) {
                return response()->json(['message' => 'Guest count exceeds cabin capacity'], 422);
            }

            if ($reservations === null) {
                return response()->json(['message' => 'Cabin not available for selected dates'], 409);
            }

            foreach ($reservations as $reservation) {
                if ($request->boolean('send_email')) {
                    $this->mail->send($reservation->email, new ReservationCreatedMail($reservation));
                }
                $this->mail->sendReservationCreatedToAdmins($reservation);
            }

            $this->includeRelatedReservations($reservations);

            return response()->json($isBatch ? [
                'reservation_group_id' => $reservations->first()->reservation_group_id,
                'reservations' => $reservations,
            ] : $reservations->first(), 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Reservation store failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
                'user_id' => optional($request->user())->id,
            ]);

            $response = ['message' => 'Error creating reservation'];
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }

    // PUT /reservations/{id}
    public function update(Request $request, $id)
    {
        $reservation = Reservation::with('cabin')->findOrFail($id);

        if ($reservation->status === 'cancelled') {
            return response()->json(['message' => 'Cancelled reservations cannot be edited'], 422);
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|in:pending,confirmed,active,cancelled',
            'payment_method' => 'sometimes|nullable|in:stripe,cash,bank_transfer,terminal,courtesy',
            'payment_reference' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string',
            'amount_paid' => 'sometimes|nullable|numeric|min:0',
            'reservation_group_id' => 'sometimes|nullable|uuid',
            'group_amount_paid' => 'prohibited',
        ]);

        $previousStatus = $reservation->status;
        if (isset($validated['reservation_group_id'])) {
            $validated['reservation_group_id'] = strtolower($validated['reservation_group_id']);
        }
        $reservation->update($validated);

        if ($previousStatus !== $reservation->status) {
            $this->mail->send($reservation->email, new ReservationStatusChangedMail($reservation, $previousStatus));
        }

        $this->includeRelatedReservations(new Collection([$reservation]));

        return response()->json($reservation, 200);
    }

    private function allocateGroupDeposit(int|float|string $amount, array $prices): array
    {
        $depositCents = (int) round((float) $amount * 100);
        $totals = array_map(fn (array $price) => (int) round($price['total'] * 100), $prices);
        $totalCents = array_sum($totals);

        if ($depositCents > $totalCents) {
            throw ValidationException::withMessages([
                'group_amount_paid' => 'The group deposit cannot exceed the total of the reservations being created.',
            ]);
        }

        $allocations = array_fill(0, count($prices), 0);
        $remainders = [];
        if ($totalCents > 0) {
            foreach ($totals as $index => $cents) {
                // Integer arithmetic also handles products larger than PHP's integer limit.
                [$share, $remainder] = BigInteger::of($depositCents)->multipliedBy($cents)
                    ->quotientAndRemainder($totalCents);
                $allocations[$index] = $share->toInt();
                $remainders[$index] = $remainder->toInt();
            }

            // Largest remainders receive the leftover cents; input order breaks ties.
            uksort($remainders, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
            $remaining = $depositCents - array_sum($allocations);
            foreach (array_keys($remainders) as $index) {
                if ($remaining === 0) {
                    break;
                }
                $allocations[$index]++;
                $remaining--;
            }
        }

        return array_map(
            fn (int $cents) => intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT),
            $allocations
        );
    }

    private function includeRelatedReservations(Collection $reservations): void
    {
        $reservations->load('groupReservations.cabin', 'groupReservations.payment');

        foreach ($reservations as $reservation) {
            $related = $reservation->groupReservations
                ->reject(fn (Reservation $related) => $related->id === $reservation->id)
                ->values();
            $reservation->unsetRelation('groupReservations');
            $reservation->setRelation('relatedReservations', $related);
        }
    }

    // DELETE /reservations/{id}
    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Reservation cancelled'], 200);
    }

    // GET /reservations/availability?cabin_id={id}&start_date={date}&end_date={date}
    public function checkAvailability(Request $request, PriceCalculatorService $priceCalculator)
    {
        $validated = $request->validate([
            'cabin_id'   => 'required|exists:cabins,id',
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date'   => 'required|date_format:Y-m-d|after:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->toDateString();
        $endDate   = Carbon::parse($validated['end_date'])->toDateString();

        $cabin = Cabin::findOrFail($validated['cabin_id']);

        $isBooked = Reservation::where('cabin_id', $validated['cabin_id'])
            ->whereIn('status', ['pending', 'confirmed', 'active'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<', $endDate)
                    ->where('end_date', '>', $startDate);
            })
            ->exists();

        $price = $priceCalculator->calculate(
            $cabin,
            Carbon::parse($startDate),
            Carbon::parse($endDate)
        );

        return response()->json([
            'available'   => !$isBooked,
            'cabin_id'    => $cabin->id,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'total_days'  => $price['nights'],
            'total_price' => $price['total'],
            'nightly_prices' => $price['nightly_prices'],
            'price_per_night' => $price['price_per_night'],
            'average_price_per_night' => $price['average_price_per_night'],
        ]);
    }
}
