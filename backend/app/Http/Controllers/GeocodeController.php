<?php

namespace App\Http\Controllers;

use App\Services\GeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeocodeController extends Controller
{
    public function __construct(
        private GeocodeService $geocodeService
    ) {}

    /**
     * Geocode an address to lat/lng using Google Geocoding API.
     * POST body: { "address": "string" } (max 500 chars)
     * Success: { "lat": float, "lng": float }
     * Not found: 422 { "message": "Address not found", "lat": null, "lng": null }
     */
    public function geocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:500'],
        ]);

        $result = $this->geocodeService->geocode($validated['address']);

        if ($result !== null) {
            return response()->json([
                'lat' => $result['lat'],
                'lng' => $result['lng'],
            ]);
        }

        return response()->json([
            'message' => 'Address not found',
            'lat' => null,
            'lng' => null,
        ], 422);
    }
}
