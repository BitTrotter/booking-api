<?php

namespace App\Http\Controllers\Features;
use App\Models\Feature;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        $features = Feature::all();
        return response()->json($features);
    }

    public function show($id)
    {
        $feature = Feature::findOrFail($id);
        return response()->json($feature);
    }

    public function store(Request $request)
    {
        try {
        $validated = $request->validate([
            'name' => 'required|string',
            
        ]);

        $feature = Feature::create($validated);
        return response()->json($feature, 201);
    }
    catch (\Exception $e) {
        return response()->json(['message' => 'Error creating feature', 'error' => $e->getMessage()], 500);
    }
    }
    public function update(Request $request, $id)
    {
        $feature = Feature::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'icon' => 'sometimes|string',
        ]);

        $feature->update($validated);
        return response()->json($feature);
    }
    public function destroy($id)
    {
        $feature = Feature::findOrFail($id);
        $feature->delete();
        return response()->json(null, 204);
    }
}
