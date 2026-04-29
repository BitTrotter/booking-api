<?php

namespace App\Http\Controllers\Cabins;

use App\Http\Controllers\Controller;
use App\Models\Cabin;
use App\Models\CabinImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CabinImageController extends Controller
{
    public function store(Request $request, $cabinId)
    {
        $request->validate([
            'image' => 'nullable|image|max:4096',
            'images' => 'nullable|array',
            'images.*' => 'image|max:4096',
            'is_cover' => 'nullable|boolean',
        ]);

        $cabin = Cabin::findOrFail($cabinId);
        $files = $this->extractFiles($request);

        if (empty($files)) {
            return response()->json([
                'message' => 'You must upload at least one image using image or images[].',
            ], 422);
        }

        $isCover = filter_var($request->input('is_cover', false), FILTER_VALIDATE_BOOLEAN);

        $images = DB::transaction(function () use ($files, $cabin, $isCover) {
            if ($isCover) {
                CabinImage::where('cabin_id', $cabin->id)->update(['is_main' => false]);
            }

            return collect($files)->values()->map(function ($file, $index) use ($cabin, $isCover) {
                $path = $file->store("cabins/{$cabin->id}", 'public');

                return CabinImage::create([
                    'cabin_id' => $cabin->id,
                    'url' => $path,
                    'is_main' => $isCover && $index === 0,
                ]);
            });
        });

        return response()->json([
            'message' => 'Images uploaded successfully',
            'images' => $images->map(fn(CabinImage $image) => $this->transformImage($image)),
        ], 201);
    }

    public function index($cabinId)
    {
        $cabin = Cabin::with('images')->findOrFail($cabinId);

        return response()->json([
            'images' => $cabin->images->map(fn(CabinImage $image) => $this->transformImage($image)),
        ], 200);
    }

    public function destroy($cabinId, $imageId)
    {
        $image = CabinImage::where('cabin_id', $cabinId)->findOrFail($imageId);

        Storage::disk('public')->delete($image->url);
        $image->delete();

        return response()->json(['message' => 'Image deleted successfully'], 200);
    }

    private function extractFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('image')) {
            $files[] = $request->file('image');
        }

        if ($request->hasFile('images')) {
            $files = [...$files, ...$request->file('images')];
        }

        return $files;
    }

    private function transformImage(CabinImage $image): array
    {
        return [
            'id' => $image->id,
            'cabin_id' => $image->cabin_id,
            'url' => $image->url,
            'public_url' => Storage::disk('public')->url($image->url),
            'is_main' => (bool) $image->is_main,
            'created_at' => $image->created_at,
        ];
    }
}
