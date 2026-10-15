<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CabinSpecialPriceController extends Controller
{
    public function index(Cabin $cabin): JsonResponse
    {
        return response()->json($cabin->specialPrices()->orderBy('date')->get());
    }

    public function upsert(Request $request, Cabin $cabin, string $date): JsonResponse
    {
        $this->validateDate($date);
        $validated = $request->validate([
            'price_per_night' => 'required|numeric|min:0|max:999999.99|decimal:0,2',
        ]);

        return DB::transaction(function () use ($cabin, $date, $validated) {
            // Serialize tariff changes with reservation creation for this cabin.
            $lockedCabin = Cabin::whereKey($cabin->id)->lockForUpdate()->firstOrFail();
            $price = $lockedCabin->specialPrices()->updateOrCreate(['date' => $date], $validated);

            return response()->json($price, $price->wasRecentlyCreated ? 201 : 200);
        });
    }

    public function destroy(Cabin $cabin, string $date): JsonResponse
    {
        $this->validateDate($date);

        return DB::transaction(function () use ($cabin, $date) {
            $lockedCabin = Cabin::whereKey($cabin->id)->lockForUpdate()->firstOrFail();
            $price = $lockedCabin->specialPrices()->where('date', $date)->firstOrFail();
            $price->delete();

            return response()->json(['message' => 'Special price deleted']);
        });
    }

    private function validateDate(string $date): void
    {
        Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
    }
}
