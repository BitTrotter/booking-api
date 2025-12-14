<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use App\Services\PriceCalculatorService;
use Illuminate\Http\Request;
use Carbon\Carbon;


class CabinPriceController extends Controller
{
    public function calculate(
        
        Request $request,
        Cabin $cabin,
        PriceCalculatorService $priceCalculator
    ) {
        $validated = $request->validate([
            'check_in'  => 'required|date',
            'check_out' => 'required|date|after:check_in',
        ]);

        $result = $priceCalculator->calculate(
            $cabin,
            Carbon::parse($validated['check_in']),
            Carbon::parse($validated['check_out'])
        );

        return response()->json($result, 200);
    }
}
