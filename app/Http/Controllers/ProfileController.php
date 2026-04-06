<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employee_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'face_front_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'face_left_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'face_right_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        if (array_key_exists('full_name', $data)) {
            $user->name = $data['full_name'];
        }

        if (array_key_exists('avatar_url', $data)) {
            $user->avatar_url = $data['avatar_url'];
        }

        if (array_key_exists('face_front_url', $data)) {
            $user->face_front_url = $data['face_front_url'];
        }
        if (array_key_exists('face_left_url', $data)) {
            $user->face_left_url = $data['face_left_url'];
        }
        if (array_key_exists('face_right_url', $data)) {
            $user->face_right_url = $data['face_right_url'];
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
     * Expects multipart/form-data with fields "face_photo" and "position" (front|left|right).
     * Replaces any existing photo for that position (old file is deleted).
     * Returns the public URL as { data: "<url>" }.
     */
    public function uploadFacePhoto(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'face_photo' => ['required', 'file', 'image', 'max:5120'], // 5 MB
            'position' => ['required', 'string', 'in:front,left,right'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['face_photo'];
        $position = $data['position'];
        $column = "face_{$position}_url";

        $disk = config('filesystems.default');
        $directory = 'face-photos/'.$user->id;
        $extension = $file->extension();

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        // Delete previous file for this position if it exists (cleanup orphaned files)
        $oldUrl = $user->$column;
        if ($oldUrl) {
            $baseUrl = rtrim($storage->url(''), '/');
            $oldPath = $baseUrl !== '' ? str_replace($baseUrl.'/', '', $oldUrl) : $oldUrl;
            $storage->delete($oldPath);
        }

        // Fixed filename per position → overwrites in place for same extension
        $path = $file->storeAs($directory, "face_{$position}.{$extension}", $disk);

        if (! $path) {
            throw ValidationException::withMessages([
                'face_photo' => ['Failed to store face photo.'],
            ]);
        }

        $url = $storage->url($path);
        $user->$column = $url;
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
            'id' => (string) $user->id,
            'userId' => (string) $user->id,
            'fullName' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first() ?? 'staff',
            'phone' => null,
            'department' => null,
            'employeeId' => null,
            'avatarUrl' => $user->avatar_url ?? null,
            'faceFrontUrl' => $user->face_front_url ?? null,
            'faceLeftUrl' => $user->face_left_url ?? null,
            'faceRightUrl' => $user->face_right_url ?? null,
            'officeId' => null,
            'managerId' => null,
            'createdAt' => optional($user->created_at)->toIso8601String(),
            'updatedAt' => optional($user->updated_at)->toIso8601String(),
            'office' => null,
        ];
    }
}
