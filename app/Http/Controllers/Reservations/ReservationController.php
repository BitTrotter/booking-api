<?php

namespace App\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Mail\ReservationCreatedMail;
use App\Mail\ReservationStatusChangedMail;
use App\Models\Cabin;
use App\Models\Reservation;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    public function __construct(private MailService $mail) {}
    // GET /reservations
    public function index()
    {
        $reservations = Reservation::with(['cabin', 'user', 'guests', 'payment'])->get();
        return response()->json($reservations, 200);
    }

    // GET /reservations/{id}
    public function show($id)
    {
        $reservation = Reservation::with(['cabin', 'user', 'guests', 'payment'])->findOrFail($id);
        return response()->json($reservation, 200);
    }

    // POST /reservations
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'cabin_id' => 'required|exists:cabins,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
                'email'  => 'required|email|max:255',
                'phone'  => 'required|string|max:20',
                'guests' => 'required|array|min:1',
                'guests.*.full_name' => 'required|string|max:150',
                'guests.*.guest_type' => 'required|in:adult,child',
            ]);

            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $reservation = DB::transaction(function () use ($validated, $user) {
                $startDate = Carbon::parse($validated['start_date'])->toDateString();
                $endDate = Carbon::parse($validated['end_date'])->toDateString();

                $cabin = Cabin::where('id', $validated['cabin_id'])->lockForUpdate()->firstOrFail();

                // Solape real: existing.start < new.end AND existing.end > new.start
                $existing = Reservation::where('cabin_id', $validated['cabin_id'])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<', $endDate)
                            ->where('end_date', '>', $startDate);
                    })
                    ->exists();

                if ($existing) {
                    return null;
                }

                $days = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                $total = $days * $cabin->price_per_night;
                $guestCount = count($validated['guests']);

                if ($guestCount > $cabin->capacity) {
                    return false;
                }

                $reservation = Reservation::create([
                    'user_id'    => $user->id,
                    'created_by' => $user->id,
                    'cabin_id'   => $validated['cabin_id'],
                    'start_date' => $startDate,
                    'end_date'   => $endDate,
                    'guest_count' => $guestCount,
                    'email'      => $validated['email'],
                    'phone'      => $validated['phone'],
                    'total_days' => $days,
                    'total_price' => $total,
                    'status'     => 'pending',
                ]);

                $reservation->guests()->createMany($validated['guests']);

                return $reservation->load(['cabin', 'user', 'guests']);
            });

            if ($reservation === false) {
                return response()->json(['message' => 'Guest count exceeds cabin capacity'], 422);
            }

            if ($reservation === null) {
                return response()->json(['message' => 'Cabin not available for selected dates'], 409);
            }

            $this->mail->send($reservation->email, new ReservationCreatedMail($reservation));

            return response()->json($reservation, 201);
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
        $reservation = Reservation::with(['cabin', 'guests'])->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled'
        ]);

        $previousStatus = $reservation->status;
        $reservation->update($validated);

        if ($previousStatus !== $reservation->status) {
            $this->mail->send($reservation->email, new ReservationStatusChangedMail($reservation, $previousStatus));
        }

        return response()->json($reservation, 200);
    }

    // DELETE /reservations/{id}
    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();

        return response()->json(['message' => 'Reservation cancelled'], 200);
    }

    // GET /reservations/availability?cabin_id={id}&start_date={date}&end_date={date}
    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'cabin_id'   => 'required|exists:cabins,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date'])->toDateString();
        $endDate   = Carbon::parse($validated['end_date'])->toDateString();

        $cabin = Cabin::findOrFail($validated['cabin_id']);

        $isBooked = Reservation::where('cabin_id', $validated['cabin_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<', $endDate)
                      ->where('end_date', '>', $startDate);
            })
            ->exists();

        $days  = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        $total = $days * $cabin->price_per_night;

        return response()->json([
            'available'   => !$isBooked,
            'cabin_id'    => $cabin->id,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'total_days'  => $days,
            'total_price' => $total,
        ]);
    }
}
