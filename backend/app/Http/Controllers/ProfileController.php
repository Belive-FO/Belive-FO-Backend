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
            'fullName'      => ['sometimes', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'department'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'employeeId'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'avatarUrl'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'faceFrontUrl'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'faceLeftUrl'   => ['sometimes', 'nullable', 'string', 'max:2048'],
            'faceRightUrl'  => ['sometimes', 'nullable', 'string', 'max:2048'],
        ]);

        if (array_key_exists('fullName', $data)) {
            $user->name = $data['fullName'];
        }

        if (array_key_exists('avatarUrl', $data)) {
            $user->avatar_url = $data['avatarUrl'];
        }

        if (array_key_exists('faceFrontUrl', $data)) {
            $user->face_front_url = $data['faceFrontUrl'];
        }
        if (array_key_exists('faceLeftUrl', $data)) {
            $user->face_left_url = $data['faceLeftUrl'];
        }
        if (array_key_exists('faceRightUrl', $data)) {
            $user->face_right_url = $data['faceRightUrl'];
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
            'position'   => ['required', 'string', 'in:front,left,right'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $data['face_photo'];
        $position = $data['position'];
        $column = "face_{$position}_url";

        $disk      = config('filesystems.default');
        $directory = 'face-photos/' . $user->id;
        $extension = $file->extension();

        // Delete previous file for this position if it exists (cleanup orphaned files)
        $oldUrl = $user->$column;
        if ($oldUrl) {
            $baseUrl = rtrim(Storage::disk($disk)->url(''), '/');
            $oldPath = $baseUrl !== '' ? str_replace($baseUrl . '/', '', $oldUrl) : $oldUrl;
            Storage::disk($disk)->delete($oldPath);
        }

        // Fixed filename per position → overwrites in place for same extension
        $path = $file->storeAs($directory, "face_{$position}.{$extension}", $disk);

        if (! $path) {
            throw ValidationException::withMessages([
                'face_photo' => ['Failed to store face photo.'],
            ]);
        }

        $url = Storage::disk($disk)->url($path);
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
            'id'           => (string) $user->id,
            'userId'       => (string) $user->id,
            'fullName'     => $user->name,
            'email'        => $user->email,
            'phone'        => null,
            'department'   => null,
            'employeeId'   => null,
            'avatarUrl'    => $user->avatar_url ?? null,
            'faceFrontUrl' => $user->face_front_url ?? null,
            'faceLeftUrl'  => $user->face_left_url ?? null,
            'faceRightUrl' => $user->face_right_url ?? null,
            'officeId'     => null,
            'managerId'    => null,
            'createdAt'    => optional($user->created_at)->toIso8601String(),
            'updatedAt'    => optional($user->updated_at)->toIso8601String(),
            'office'       => null,
        ];
    }
}
