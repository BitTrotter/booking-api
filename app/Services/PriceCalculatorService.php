<?php
namespace App\Services;

use Carbon\Carbon;
use App\Models\Cabin;
use App\Models\CabinPriceRule;


class PriceCalculatorService
{
    public function calculate(Cabin $cabin, Carbon $checkIn, Carbon $checkOut)
    {
        $nights = $checkIn->diffInDays($checkOut);

        $rules = $cabin->priceRules()
            ->where('active', true)
            ->orderByRaw("
                CASE type
                    WHEN 'date_range' THEN 1
                    WHEN 'weekend' THEN 2
                    WHEN 'min_nights' THEN 3
                    ELSE 4
                END
            ")
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleApplies($rule, $checkIn, $checkOut, $nights)) {
                return [
                    'total' => $rule->price_per_night * $nights,
                    'price_per_night' => $rule->price_per_night,
                    'rule_applied' => $rule->type,
                    'nights' => $nights,
                ];
            }
        }

        // fallback al precio base
        return [
            'total' => $cabin->price_per_night * $nights,
            'price_per_night' => $cabin->price_per_night,
            'rule_applied' => 'base',
            'nights' => $nights,
        ];
    }

    /**
     * Determina si una regla aplica a la reservación
     */
    private function ruleApplies(
        CabinPriceRule $rule,
        Carbon $checkIn,
        Carbon $checkOut,
        int $nights
    ): bool {
        return match ($rule->type) {
            'weekend'   => $this->weekendRuleApplies($rule, $checkIn, $checkOut),
            'date_range'=> $this->dateRangeRuleApplies($rule, $checkIn, $checkOut),
            'min_nights'=> $this->minNightsRuleApplies($rule, $nights),
            default     => false,
        };
    }




    private function weekendRuleApplies(
    CabinPriceRule $rule,
    Carbon $checkIn,
    Carbon $checkOut
): bool {
    $days = collect($rule->days ?? [])
        ->map(fn ($d) => strtolower($d));

    $current = $checkIn->copy();

    while ($current < $checkOut) {
        if ($days->contains(strtolower($current->format('l')))) {
            return true;
        }
        $current->addDay();
    }

    return false;
}

private function dateRangeRuleApplies(
    CabinPriceRule $rule,
    Carbon $checkIn,
    Carbon $checkOut
): bool {
    if (!$rule->start_date || !$rule->end_date) {
        return false;
    }

    return $checkIn->lte($rule->end_date)
        && $checkOut->gte($rule->start_date);
}

private function minNightsRuleApplies(
    CabinPriceRule $rule,
    int $nights
): bool {
    return $rule->min_nights && $nights >= $rule->min_nights;
}

}