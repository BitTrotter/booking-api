<?php

namespace App\Services;

use App\Models\Cabin;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PriceCalculatorService
{
    public function calculate(Cabin $cabin, Carbon $checkIn, Carbon $checkOut): array
    {
        $current = $checkIn->copy()->startOfDay();
        $departure = $checkOut->copy()->startOfDay();

        if ($departure->lte($current)) {
            throw ValidationException::withMessages([
                'check_out' => 'The check out date must be after check in.',
            ]);
        }

        $totalCents = 0;
        $nightlyPrices = [];
        $weeklyPrices = $cabin->weekly_prices ?? [];

        while ($current->lt($departure)) {
            $day = strtolower($current->format('l'));
            $cents = (int) round((float) ($weeklyPrices[$day] ?? $cabin->price_per_night) * 100);
            $totalCents += $cents;
            $nightlyPrices[] = [
                'date' => $current->toDateString(),
                'day' => $day,
                'price' => $cents / 100,
            ];
            $current->addDay();
        }

        $nights = count($nightlyPrices);
        $uniformPrice = count(array_unique(array_column($nightlyPrices, 'price'))) === 1;

        return [
            'total' => $totalCents / 100,
            'price_per_night' => $uniformPrice ? $nightlyPrices[0]['price'] : null,
            'average_price_per_night' => round($totalCents / $nights) / 100,
            'nights' => $nights,
            'nightly_prices' => $nightlyPrices,
        ];
    }
}
