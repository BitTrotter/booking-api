<?php

namespace App\Http\Controllers\Cabins;

use App\Models\Cabin;
use App\Models\CabinImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class CabinImageController extends Controller
{
    // SUBIR FOTO A UNA CABAÑA
    public function store(Request $request, $cabinId)
    {
      
        try{
            
        
        $request->validate([
            'image' => 'required|image|max:4096', // 4MB
            'is_cover' => 'nullable|boolean'
        ]);

        $cabin = Cabin::findOrFail($cabinId);

        // Guardar la imagen
        $path = $request->file('image')->store("cabins/{$cabinId}", 'public');
        

        // Si marcaron la imagen como portada, hacer reset
        if ($request->is_cover == true) {
            CabinImage::where('cabin_id', $cabinId)->update(['is_cover' => false]);
        }

        // Guardar en la BD
        $image = CabinImage::create([
            'cabin_id' => $cabinId,
            'url' => $path,
            'is_main' => $request->is_cover ?? false
        ]);

        return response()->json($image, 201);
    }
    catch (\Exception $e) {
        return response()->json(['message' => 'Error uploading image', 'error' => $e->getMessage()], 500);
    }
    }

    // LISTAR IMÁGENES DE UNA CABAÑA
    public function index($cabinId)
    {
        
        try {
        $cabin = Cabin::findOrFail($cabinId);
        return response()->json($cabin->images, 200);
        }
        catch (\Exception $e) {
            return response()->json(['message' => 'Error fetching images', 'error' => $e->getMessage()], 500);
        }
    }

    // ELIMINAR UNA IMAGEN
    public function destroy($cabinId, $imageId)
    {
        $image = CabinImage::where('cabin_id', $cabinId)->findOrFail($imageId);

        // Borrar archivo del storage
        Storage::disk('public')->delete($image->image_url);

        // Borrar registro
        $image->delete();

        return response()->json(['message' => 'Imagen eliminada correctamente'], 200);
    }
}
