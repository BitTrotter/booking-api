<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cabin;

class CabinController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       return Cabin::orderBy("id","desc")->get();   
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $validated = $request->validate([
        'name' => 'required|string',
        'description' => 'nullable|string',
        'price_per_night' => 'required|numeric|min:0',
        'capacity' => 'required|integer|min:1',
        'beds' => 'required|integer|min:1',
        'bathrooms' => 'required|integer|min:1',
        'services' => 'nullable|array',
        'status' => 'in:available,maintenance'
    ]);

    $cabin = Cabin::create($validated);

    return response()->json([
        'message' => 'Cabin created',
        'cabin' => $cabin
    ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Cabin::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
        'name' => 'sometimes|required|string',
        'description' => 'sometimes|nullable|string',
        'price_per_night' => 'sometimes|required|numeric|min:0',
        'capacity' => 'sometimes|required|integer|min:1',
        'beds' => 'sometimes|required|integer|min:1',
        'bathrooms' => 'sometimes|required|integer|min:1',
        'services' => 'sometimes|nullable|array',
        'status' => 'sometimes|in:available,maintenance'
    ]); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $cabin = Cabin::findOrFail($id);
        $cabin->delete();

        return response()->json([
            'message' => 'Cabin deleted'
        ], 200);
    }
}
