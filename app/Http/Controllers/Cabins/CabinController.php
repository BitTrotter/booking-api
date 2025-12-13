<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use App\Models\CabinImage;
use Illuminate\Http\Request;

class CabinController extends Controller
{
    // GET /cabins
    public function index()
    {
        
        $cabins = Cabin::with(['features', 'images'])->get();
        return response()->json($cabins, 200);
    }

    // GET /cabins/{id}
    public function show($id)
    {
        $cabin = Cabin::with(['features', 'images'])->findOrFail($id);
        return response()->json($cabin, 200);
    }

    // POST /cabins
    public function store(Request $request)
    {


        $validated = $request->validate([
            'name'            => 'required|string',
            'description'     => 'nullable|string',
            'price_per_night' => 'required|numeric',
            'capacity'        => 'required|integer',
            'beds'            => 'required|integer',
            'bathrooms'       => 'required|integer',
            'services'        => 'nullable|array',
            'status'          => 'required|string',

        ]);

        $cabin = Cabin::create($validated);

        return response()->json($cabin, 201);
    }

    // PUT /cabins/{id}
    public function update(Request $request, $id)
    {
    
        $cabin = Cabin::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'sometimes|string',
            'description'     => 'sometimes|string',
            'price_per_night' => 'sometimes|numeric',
            'capacity'        => 'sometimes|integer',
            'beds'            => 'sometimes|integer',
            'bathrooms'       => 'sometimes|integer',
            'services'        => 'sometimes|array',
            'status'          => 'sometimes|string',
        ]);

        $cabin->update($validated);

        return response()->json($cabin, 200);
    }

    // DELETE /cabins/{id}
    public function destroy($id)
    {
        $cabin = Cabin::findOrFail($id);
        $cabin->delete();

        return response()->json(['message' => 'Cabin deleted'], 200);
    }


    // POST /cabins/{id}/features
    public function assignFeatures(Request $request, $id)
    {
        try {
        $cabin = Cabin::findOrFail($id);

        $validated = $request->validate([
            'features' => 'required|array',
            'features.*' => 'integer|exists:features,id'
        ]);

        $cabin->features()->sync($validated['features']);

        return response()->json(['message' => 'Features assigned'], 200);
    }
    catch (\Exception $e) {
        return response()->json(['message' => 'Error assigning features', 'error' => $e->getMessage()], 500);
    }
    }


    // POST /cabins/{id}/images
    public function uploadImages(Request $request, $id)
    {
        $cabin = Cabin::findOrFail($id);

        $validated = $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:2048'
        ]);

        foreach ($validated['images'] as $image) {
            $path = $image->store('cabins', 'public');

            CabinImage::create([
                'cabin_id' => $cabin->id,
                'image_url' => $path,
                'is_cover' => false
            ]);
        }

        return response()->json(['message' => 'Images uploaded'], 201);
    }
}
