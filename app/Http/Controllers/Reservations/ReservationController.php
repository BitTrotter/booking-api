<?php

namespace App\Http\Controllers\Reservations;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Cabin;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    // GET /reservations
    public function index()
    {
        $reservations = Reservation::with(['cabin', 'user'])->get();
          $reservations =  $reservations->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->cabin->name,
                'start' => $r->start_date->toIso8601String(),
                'end' => $r->end_date->toIso8601String(),
                'allDay' => true,
                'url' => '',
                'extendedProps' => [
                    'calendar' => 'Business',
                    'status' => $r->status,
                    'total_price' => $r->total_price,
                    'cabin_id' => $r->cabin_id,
                    'user' => $r->user->name ?? null,
                ],
            ]);
        
        return response()->json($reservations, 200);
    }

    // GET /reservations/{id}
    public function show($id)
    {
        $reservation = Reservation::with(['cabin', 'user'])->findOrFail($id);
        return response()->json($reservation, 200);
    }



    // POST /reservations
    public function store(Request $request)
    {
        try {

            $validated = $request->validate([
                'cabin_id' => 'required|exists:cabins,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date'   => 'required|date|after:start_date',
            ]);

            // Validar disponibilidad
            $existing = Reservation::where('cabin_id', $validated['cabin_id'])
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                        ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']]);
                })
                ->exists();

            if ($existing) {
                return response()->json(['message' => 'Cabin not available'], 409);
            }

            $cabin = Cabin::find($validated['cabin_id']);

            // Calcular precio total
            $days = (new \Carbon\Carbon($validated['start_date']))
                ->diffInDays(new \Carbon\Carbon($validated['end_date']));

            $total = $days * $cabin->price_per_night;

            $reservation = Reservation::create([
                // 'user_id' =>auth()->id(),
                'user_id' => $request->user()->id,
                'cabin_id' => $validated['cabin_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_price' => $total,
                'status' => 'pending'
            ]);

            return response()->json($reservation, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error creating reservation', 'error' => $e->getMessage()], 500);
        }
    }

    // PUT /reservations/{id}
    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string'
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
