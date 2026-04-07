<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminProfileResource;
use App\Models\User;
use App\Services\AdminUserDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with(['department']);
        AdminUserDirectoryService::applyVisibilityScope($query, $request->user());
        $users = $query->orderBy('name')->get();

        return response()->json(AdminProfileResource::collection($users));
    }
}
