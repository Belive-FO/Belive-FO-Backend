<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\Office;
use App\Services\GeoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function myToday(Request $request): JsonResponse
    {
        $user = $request->user();

        $timezone = config('app.timezone', 'UTC');

        $startLocal = Carbon::today($timezone)->startOfDay();
        $endLocal   = Carbon::today($timezone)->endOfDay();

        $startUtc = $startLocal->clone()->setTimezone('UTC');
        $endUtc   = $endLocal->clone()->setTimezone('UTC');

        $logs = AttendanceLog::where('user_id', $user->id)
            ->whereBetween('timestamp', [$startUtc, $endUtc])
            ->orderBy('timestamp')
            ->get();

        return response()->json(AttendanceLogResource::collection($logs));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type'     => ['required', 'in:clock_in,clock_out'],
            'officeId' => ['required', 'string', 'exists:offices,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude'=> ['required', 'numeric', 'between:-180,180'],
            'photoUrl' => ['nullable', 'string'],
            'notes'    => ['nullable', 'string'],
        ]);

        /** @var \App\Models\Office $office */
        $office = Office::findOrFail($data['officeId']);

        $distance = GeoService::distanceInMeters(
            (float) $office->latitude,
            (float) $office->longitude,
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if ($distance > $office->radius_meters) {
            return response()->json([
                'error'   => 'OUT_OF_RADIUS',
                'message' => sprintf(
                    'You are %.1f meters from %s. You must be within %d meters to clock in/out.',
                    $distance,
                    $office->name,
                    $office->radius_meters
                ),
                'status'  => 422,
            ], 422);
        }

        // Prevent obvious double clock_in without clock_out
        if ($data['type'] === 'clock_in') {
            $lastLog = AttendanceLog::where('user_id', $user->id)
                ->orderByDesc('timestamp')
                ->first();

            if ($lastLog && $lastLog->type === 'clock_in') {
                return response()->json([
                    'error'   => 'ALREADY_CLOCKED_IN',
                    'message' => 'You are already clocked in.',
                    'status'  => 422,
                ], 422);
            }
        }

        if ($data['type'] === 'clock_out') {
            $timezone = config('app.timezone', 'UTC');
            $startLocal = Carbon::today($timezone)->startOfDay();
            $endLocal   = Carbon::today($timezone)->endOfDay();
            $startUtc   = $startLocal->clone()->setTimezone('UTC');
            $endUtc     = $endLocal->clone()->setTimezone('UTC');

            $hasClockInToday = AttendanceLog::where('user_id', $user->id)
                ->where('type', 'clock_in')
                ->whereBetween('timestamp', [$startUtc, $endUtc])
                ->exists();

            if (! $hasClockInToday) {
                return response()->json([
                    'error'   => 'NO_CLOCK_IN_TODAY',
                    'message' => 'You have not clocked in today.',
                    'status'  => 422,
                ], 422);
            }
        }

        $timestampUtc = now()->setTimezone('UTC');

        $log = AttendanceLog::create([
            'user_id'        => $user->id,
            'office_id'      => $office->id,
            'type'           => $data['type'],
            'status'         => 'valid',
            'timestamp'      => $timestampUtc,
            'latitude'       => $data['latitude'],
            'longitude'      => $data['longitude'],
            'distance_meters'=> (int) round($distance),
            'photo_url'      => $data['photoUrl'] ?? null,
            'notes'          => $data['notes'] ?? null,
        ]);

        return response()->json([
            'log'            => new AttendanceLogResource($log),
            'distanceMeters' => (int) round($distance),
        ]);
    }
}

