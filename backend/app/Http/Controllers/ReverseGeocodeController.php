<?php

namespace App\Http\Controllers;

use App\Services\GeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReverseGeocodeController extends Controller
{
    public function __construct(
        private GeocodeService $geocodeService
    ) {}

    /**
     * Reverse geocode lat/lng to a human-readable location name.
     * POST body: { "lat": float, "lng": float }
     * Success: 200 { "location_name": "..." }
     * Error: 422 { "message": "Unable to reverse geocode" }
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $locationName = $this->geocodeService->reverseGeocode(
            (float) $request->input('lat'),
            (float) $request->input('lng')
        );

        if ($locationName === null) {
            return response()->json(['message' => 'Unable to reverse geocode'], 422);
        }

        return response()->json(['location_name' => $locationName]);
    }
}
