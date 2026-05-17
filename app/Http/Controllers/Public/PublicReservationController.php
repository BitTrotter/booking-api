<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\ReservationCreatedMail;
use App\Models\Cabin;
use App\Models\Reservation;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicReservationController extends Controller
{
    public function __construct(private MailService $mail) {}

    // POST /api/public/reservations
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cabin_id'               => 'required|exists:cabins,id',
                'start_date'             => 'required|date|after_or_equal:today',
                'end_date'               => 'required|date|after:start_date',
                'email'                  => 'required|email|max:255',
                'phone'                  => 'required|string|max:20',
                'guests'                 => 'required|array|min:1',
                'guests.*.full_name'     => 'required|string|max:150',
                'guests.*.guest_type'    => 'required|in:adult,child',
            ]);

            $reservation = DB::transaction(function () use ($validated) {
                $startDate = Carbon::parse($validated['start_date'])->toDateString();
                $endDate   = Carbon::parse($validated['end_date'])->toDateString();

                $cabin = Cabin::where('id', $validated['cabin_id'])
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->firstOrFail();

                $overlaps = Reservation::where('cabin_id', $validated['cabin_id'])
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<', $endDate)
                          ->where('end_date', '>', $startDate);
                    })
                    ->exists();

                if ($overlaps) {
                    return null;
                }

                $guestCount = count($validated['guests']);

                if ($guestCount > $cabin->capacity) {
                    return false;
                }

                $days  = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                $total = $days * $cabin->price_per_night;

                $reservation = Reservation::create([
                    'user_id'     => null,
                    'created_by'  => null,
                    'cabin_id'    => $validated['cabin_id'],
                    'start_date'  => $startDate,
                    'end_date'    => $endDate,
                    'guest_count' => $guestCount,
                    'email'       => $validated['email'],
                    'phone'       => $validated['phone'],
                    'total_days'  => $days,
                    'total_price' => $total,
                    'status'      => 'pending',
                ]);

                $reservation->guests()->createMany($validated['guests']);

                return $reservation->load(['cabin', 'guests']);
            });

            if ($reservation === false) {
                return response()->json(['message' => 'Guest count exceeds cabin capacity'], 422);
            }

            if ($reservation === null) {
                return response()->json(['message' => 'Cabin not available for selected dates'], 409);
            }

            $this->mail->send($reservation->email, new ReservationCreatedMail($reservation));

            return response()->json([
                'message' => 'Reservation created successfully',
                'data'    => [
                    'id'          => $reservation->id,
                    'cabin'       => $reservation->cabin->name ?? null,
                    'start_date'  => $reservation->start_date,
                    'end_date'    => $reservation->end_date,
                    'total_days'  => $reservation->total_days,
                    'total_price' => $reservation->total_price,
                    'status'      => $reservation->status,
                    'email'       => $reservation->email,
                    'guests'      => $reservation->guests,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Public reservation store failed', [
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            $response = ['message' => 'Error creating reservation'];
            if (config('app.debug')) {
                $response['error'] = $e->getMessage();
            }

            return response()->json($response, 500);
        }
    }
}
