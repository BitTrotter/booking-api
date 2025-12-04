<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Feature;

class FeaturesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Feature::orderBy("id","desc")->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:features,name',
        ]);

        $feature = Feature::create($validated);

        return response()->json([
            'message' => 'Feature created',
            'feature' => $feature
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Feature::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'sometimes|required|string|unique:features,name,'.$id,
        ]);

        $feature = Feature::findOrFail($id);
        $feature->update($request->only('name'));

        return response()->json([
            'message' => 'Feature updated',
            'feature' => $feature
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $feature = Feature::findOrFail($id);
        $feature->delete();

        return response()->json([
            'message' => 'Feature deleted'
        ], 200);
    }
}
