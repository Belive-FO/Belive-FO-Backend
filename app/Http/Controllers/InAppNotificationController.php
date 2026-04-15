<?php

namespace App\Http\Controllers;

use App\Http\Resources\InAppNotificationResource;
use App\Models\InAppNotification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InAppNotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $rows = InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->success(
            InAppNotificationResource::collection($rows),
            'Notifications retrieved successfully.'
        );
    }

    public function markRead(Request $request, InAppNotification $inAppNotification): JsonResponse
    {
        if ($inAppNotification->user_id !== $request->user()->id) {
            abort(403);
        }

        $inAppNotification->update(['read' => true]);

        return $this->success(null, 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        InAppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('read', false)
            ->update(['read' => true, 'updated_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }
}
