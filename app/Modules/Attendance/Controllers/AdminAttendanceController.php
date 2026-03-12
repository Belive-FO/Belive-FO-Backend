<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminAttendanceLogResource;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $timezone = config('app.timezone', 'UTC');

        $startLocal = Carbon::today($timezone)->startOfDay();
        $endLocal   = Carbon::today($timezone)->endOfDay();

        $startUtc = $startLocal->clone()->setTimezone('UTC');
        $endUtc   = $endLocal->clone()->setTimezone('UTC');

        $logs = AttendanceLog::whereBetween('timestamp', [$startUtc, $endUtc])
            ->orderBy('timestamp')
            ->get();

        return response()->json(AdminAttendanceLogResource::collection($logs));
    }
}

