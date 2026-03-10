<?php

namespace App\Modules\Claims\Services;

use Illuminate\Support\Facades\Http;

class DistanceService
{
    private const BASE_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';

    /**
     * Calculate driving distance in km between two addresses using Google Distance Matrix API.
     * Returns distance in km (2 decimal places) or null if route not found.
     */
    public function calculateDrivingDistanceKm(string $from, string $to): ?float
    {
        $key = config('services.google_maps.api_key');
        if (empty($key)) {
            return null;
        }

        $response = Http::get(self::BASE_URL, [
            'origins' => $from,
            'destinations' => $to,
            'mode' => 'driving',
            'key' => $key,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        $status = $body['status'] ?? '';

        if ($status !== 'OK') {
            return null;
        }

        $rows = $body['rows'] ?? [];
        $element = $rows[0]['elements'][0] ?? null;

        if (! $element || ($element['status'] ?? '') !== 'OK') {
            return null;
        }

        $meters = (float) ($element['distance']['value'] ?? 0);
        if ($meters <= 0) {
            return null;
        }

        return round($meters / 1000, 2);
    }
}
