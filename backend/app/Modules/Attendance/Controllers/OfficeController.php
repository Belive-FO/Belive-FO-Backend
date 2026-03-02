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

        if ($request->filled('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $offices = $query->orderBy('name')->get();

        return response()->json(OfficeResource::collection($offices));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['required', 'numeric', 'between:-90,90'],
            'longitude'    => ['required', 'numeric', 'between:-180,180'],
            'radiusMeters' => ['required', 'integer', 'min:1'],
            'isActive'     => ['required', 'boolean'],
            'timezone'     => ['nullable', 'string', 'max:255'],
        ]);

        $office = Office::create([
            'name'          => $data['name'],
            'address'       => $data['address'] ?? null,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radiusMeters'],
            'is_active'     => $data['isActive'],
            'timezone'      => $data['timezone'] ?? null,
        ]);

        return response()->json(new OfficeResource($office), 201);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $office = Office::findOrFail($id);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'latitude'     => ['required', 'numeric', 'between:-90,90'],
            'longitude'    => ['required', 'numeric', 'between:-180,180'],
            'radiusMeters' => ['required', 'integer', 'min:1'],
            'isActive'     => ['required', 'boolean'],
            'timezone'     => ['nullable', 'string', 'max:255'],
        ]);

        $office->update([
            'name'          => $data['name'],
            'address'       => $data['address'] ?? null,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'radius_meters' => $data['radiusMeters'],
            'is_active'     => $data['isActive'],
            'timezone'      => $data['timezone'] ?? null,
        ]);

        return response()->json(new OfficeResource($office));
    }

    public function toggleActive(int $id, Request $request): JsonResponse
    {
        $office = Office::findOrFail($id);

        $data = $request->validate([
            'isActive' => ['required', 'boolean'],
        ]);

        $office->is_active = $data['isActive'];
        $office->save();

        return response()->json(new OfficeResource($office));
    }
}

