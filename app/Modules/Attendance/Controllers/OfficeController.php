<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Office::query();

        $canViewInactive = $request->user()?->hasAnyRole(['top_management', 'hr_admin']) === true;

        if ($request->filled('is_active') && $canViewInactive) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        } else {
            $query->where('is_active', true);
        }

        $offices = $query->orderBy('name')->get();

        return response()->json(['data' => OfficeResource::collection($offices)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $office = Office::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
            'is_active' => $data['is_active'],
            'timezone' => $data['timezone'] ?? null,
        ]);

        return response()->json(['data' => new OfficeResource($office)], 201);
    }

    public function update(Office $office, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $office->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'radius_meters' => $data['radius_meters'],
            'is_active' => $data['is_active'],
            'timezone' => $data['timezone'] ?? null,
        ]);

        return response()->json(['data' => new OfficeResource($office)]);
    }

    public function toggleActive(Office $office, Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $office->is_active = $data['is_active'];
        $office->save();

        return response()->json([
            'data' => [
                'id' => $office->id,
                'is_active' => $office->is_active,
            ],
        ]);
    }
}
