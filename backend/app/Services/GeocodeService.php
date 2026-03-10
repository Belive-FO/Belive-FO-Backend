<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeocodeService
{
    private const BASE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Get lat/lng for an address using Google Geocoding API.
     * Returns ['lat' => float, 'lng' => float] or null if not found / API error.
     */
    public function geocode(string $address): ?array
    {
        $key = config('services.google_maps.api_key');
        if (empty($key)) {
            return null;
        }

        $response = Http::get(self::BASE_URL, [
            'address' => $address,
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

        $results = $body['results'] ?? [];
        $first = $results[0] ?? null;
        $location = $first['geometry']['location'] ?? null;

        if (! $location || ! isset($location['lat'], $location['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $location['lat'],
            'lng' => (float) $location['lng'],
        ];
    }

    /**
     * Reverse geocode lat/lng to a human-readable location name using Google Geocoding API.
     * Builds a short name from address_components (POI → route → sublocality → locality).
     * Fallback: first 3 parts of formatted_address.
     * Returns null if not found or API/HTTP error.
     */
    public function reverseGeocode(float $lat, float $lng): ?string
    {
        $key = config('services.google_maps.api_key');
        if (empty($key)) {
            return null;
        }

        $response = Http::get(self::BASE_URL, [
            'latlng' => "{$lat},{$lng}",
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

        $first = $body['results'][0] ?? null;
        if (! $first) {
            return null;
        }

        $components = $first['address_components'] ?? [];
        $hasPoi = $this->hasType($components, ['point_of_interest', 'establishment']);

        // Priority: point_of_interest/establishment → route → sublocality → locality (only if no POI)
        $parts = [];
        foreach (['point_of_interest', 'establishment', 'route', 'sublocality', 'sublocality_level_1', 'locality'] as $type) {
            if ($type === 'locality' && $hasPoi) {
                continue;
            }
            $name = $this->getLongNameForType($components, $type);
            if ($name !== null && $name !== '' && ! in_array($name, $parts, true)) {
                $parts[] = $name;
            }
        }

        if ($parts !== []) {
            return implode(', ', array_slice($parts, 0, 4));
        }

        // Fallback: first 3 comma-separated parts of formatted_address
        $formatted = $first['formatted_address'] ?? '';
        $formattedParts = array_map('trim', explode(',', $formatted));
        $fallback = implode(', ', array_slice($formattedParts, 0, 3));

        return $fallback !== '' ? $fallback : null;
    }

    private function hasType(array $components, array $types): bool
    {
        foreach ($components as $c) {
            if (array_intersect($types, $c['types'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    private function getLongNameForType(array $components, string $type): ?string
    {
        foreach ($components as $c) {
            if (in_array($type, $c['types'] ?? [], true)) {
                return $c['long_name'] ?? null;
            }
        }

        return null;
    }
}
