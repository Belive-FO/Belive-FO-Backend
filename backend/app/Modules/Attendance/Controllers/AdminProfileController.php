<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::orderBy('name')->get();

        return response()->json(AdminProfileResource::collection($users));
    }
}

