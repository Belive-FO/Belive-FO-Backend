<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LarkAuthController extends Controller
{
    /**
     * Handle Lark OAuth callback
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            // 1. Exchange code for access token
            /** @var \Illuminate\Http\Client\Response $tokenResponse */
            $tokenResponse = Http::post('https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal', [
                'app_id' => config('services.lark.app_id'),
                'app_secret' => config('services.lark.app_secret'),
            ]);

            if (!$tokenResponse->successful()) {
                Log::error('Lark token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body(),
                ]);
                return response()->json(['error' => 'Failed to authenticate with Lark'], 401);
            }

            $accessToken = $tokenResponse->json('tenant_access_token');

            // 2. Get user info from Lark
            /** @var \Illuminate\Http\Client\Response $userResponse */
            $userResponse = Http::withToken($accessToken)
                ->get('https://open.larksuite.com/open-apis/authen/v1/access_token', [
                    'grant_type' => 'authorization_code',
                    'code' => $request->code,
                ]);

            if (!$userResponse->successful()) {
                Log::error('Lark user info fetch failed', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body(),
                ]);
                return response()->json(['error' => 'Failed to fetch user info from Lark'], 401);
            }

            $larkUser = $userResponse->json('data');

            // 3. Find or create user
            $user = User::firstOrCreate(
                ['lark_user_id' => $larkUser['user_id']],
                [
                    'email' => $larkUser['email'] ?? null,
                    'name' => $larkUser['name'] ?? 'Unknown',
                    'lark_open_id' => $larkUser['open_id'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            // Update user if email or name changed
            if ($user->wasRecentlyCreated === false) {
                $user->update([
                    'email' => $larkUser['email'] ?? $user->email,
                    'name' => $larkUser['name'] ?? $user->name,
                    'lark_open_id' => $larkUser['open_id'] ?? $user->lark_open_id,
                ]);
            }

            // 4. Create session (Sanctum SPA mode)
            Auth::login($user);

            return response()->json([
                'user' => $user->load('roles'),
                'message' => 'Login successful',
            ]);
        } catch (\Exception $e) {
            Log::error('Lark authentication error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $request->session()->regenerate();

        $user = $request->user()->load('roles');

        return response()->json([
            'user' => $user,
            'message' => 'Login successful',
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
