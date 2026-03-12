<?php

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'selfieBase64' => ['required', 'string'], // Size limits can be enforced at web server level if needed
            'avatarUrl'    => ['nullable', 'string'],
        ]);

        // TODO: Integrate with a real face verification provider.
        // For now, always return a successful mock result so the frontend can proceed.

        return response()->json(
            [
                'faceDetected' => true,
                'match'        => true,
                'confidence'   => 90,
                'reason'       => 'Mock verification success.',
            ]
        );
    }
}

