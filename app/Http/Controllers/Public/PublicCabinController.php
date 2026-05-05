<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\CabinDetailResource;
use App\Http\Resources\Public\CabinListResource;
use App\Models\Cabin;
use Illuminate\Http\JsonResponse;

class PublicCabinController extends Controller
{
    public function index(): JsonResponse
    {
        $cabins = Cabin::with(['features', 'images'])
            ->where('status', 'available')
            ->get();

        return response()->json([
            'data' => CabinListResource::collection($cabins),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $cabin = Cabin::with(['features', 'images'])
            ->where('status', 'available')
            ->findOrFail($id);

        return response()->json([
            'data' => new CabinDetailResource($cabin),
        ]);
    }
}
