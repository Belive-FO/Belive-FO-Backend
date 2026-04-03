<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MapsConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'key' => config('services.google_maps.api_key'),
        ]);
    }
}
