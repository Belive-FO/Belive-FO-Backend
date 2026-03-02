<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->buildProfile($user),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'fullName'     => ['sometimes', 'string', 'max:255'],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'department'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'employeeId'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatarUrl'    => ['sometimes', 'nullable', 'string', 'max:2048'],
            'facePhotoUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        if (array_key_exists('fullName', $data)) {
            $user->name = $data['fullName'];
        }

        if (array_key_exists('avatarUrl', $data)) {
            $user->avatar_url = $data['avatarUrl'];
        }

        if (array_key_exists('facePhotoUrl', $data)) {
            $user->face_photo_url = $data['facePhotoUrl'];
        }

        // phone, department, employeeId are not persisted in the users table yet.
        $user->save();

        return response()->json([
            'data' => $this->buildProfile($user),
        ]);
    }

    /**
     * Upload a face photo for the authenticated user.
     *
     * Expects multipart/form-data with field "face_photo".
     * Returns the public URL as { data: "<url>" }.
     */
    public function uploadFacePhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'face_photo' => ['required', 'file', 'image', 'max:5120'], // 5 MB
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['face_photo'];

        $disk      = config('filesystems.default');
        $directory = 'face-photos/' . $user->id;

        $path = $file->store($directory, $disk);

        if (! $path) {
            throw ValidationException::withMessages([
                'face_photo' => ['Failed to store face photo.'],
            ]);
        }

        $url = Storage::disk($disk)->url($path);

        $user->face_photo_url = $url;
        $user->save();

        return response()->json([
            'data' => $url,
        ]);
    }

    /**
     * Build the profile array for a given user.
     */
    private function buildProfile(User $user): array
    {
        return [
            'id'           => (string) $user->id,
            'userId'       => (string) $user->id,
            'fullName'     => $user->name,
            'email'        => $user->email,
            'phone'        => null,
            'department'   => null,
            'employeeId'   => null,
            'avatarUrl'    => $user->avatar_url ?? null,
            'facePhotoUrl' => $user->face_photo_url ?? null,
            'officeId'     => null,
            'managerId'    => null,
            'createdAt'    => optional($user->created_at)->toIso8601String(),
            'updatedAt'    => optional($user->updated_at)->toIso8601String(),
            'office'       => null,
        ];
    }
}
