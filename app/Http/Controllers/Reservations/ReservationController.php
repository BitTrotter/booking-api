<?php

namespace App\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Cabin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReservationController extends Controller
{
    // GET /reservations
    public function index()
    {
        $reservations = Reservation::with(['cabin', 'user', 'guests'])->get();
        return response()->json($reservations, 200);
    }

    // GET /reservations/{id}
    public function show($id)
    {
        $reservation = Reservation::with(['cabin', 'user', 'guests'])->findOrFail($id);
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
                    'user_id' => $user->id,
                    'cabin_id' => $validated['cabin_id'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'guest_count' => $guestCount,
                    'total_days' => $days,
                    'total_price' => $total,
                    'status' => 'pending',
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
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled'
        ]);

        $reservation->update($validated);

        return response()->json($reservation, 200);
    }

    // DELETE /reservations/{id}
    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->delete();

        return response()->json(['message' => 'Reservation cancelled'], 200);
    }
}
