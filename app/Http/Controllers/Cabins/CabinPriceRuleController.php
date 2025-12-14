<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use App\Models\CabinPriceRule;
use Illuminate\Http\Request;

class CabinPriceRuleController extends Controller
{
    public function index(Cabin $cabin)
    {
        return response()->json(
            $cabin->priceRules()
            ->orderBy('created_at', 'desc')
            ->get(), 200

        );
    }
       public function store(Request $request, Cabin $cabin)
    {
        $data = $request->validate([
            'type' => 'required|in:weekend,date_range,min_nights',
            'price_per_night' => 'required|numeric|min:0',
            'days' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_nights' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ]);

        $data['cabin_id'] = $cabin->id;

        $rule = CabinPriceRule::create($data);

        return response()->json($rule, 201);
    }

    /**
     * PUT /price-rules/{priceRule}
     */
    public function update(Request $request, CabinPriceRule $priceRule)
    {
        $data = $request->validate([
            'type' => 'sometimes|in:weekend,date_range,min_nights',
            'price_per_night' => 'sometimes|numeric|min:0',
            'days' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_nights' => 'nullable|integer|min:1',
            'active' => 'boolean',
        ]);

        $priceRule->update($data);

        return response()->json($priceRule, 200);
    }

    /**
     * DELETE /price-rules/{priceRule}
     */
    public function destroy(CabinPriceRule $priceRule)
    {
        $priceRule->delete();

        return response()->json(null, 204);
    }
}
