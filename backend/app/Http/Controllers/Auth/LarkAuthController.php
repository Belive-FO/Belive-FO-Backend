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
                    'json' => $tokenResponse->json(),
                ]);
                
                return response()->json([
                    'error' => 'Failed to authenticate with Lark',
                    'details' => config('app.debug') ? $tokenResponse->json() : null,
                ], 401);
            }

            $accessToken = $tokenResponse->json('tenant_access_token');
            
            if (!$accessToken) {
                Log::error('Lark tenant access token missing', [
                    'response' => $tokenResponse->json(),
                ]);
                return response()->json([
                    'error' => 'Failed to get access token from Lark',
                    'details' => config('app.debug') ? $tokenResponse->json() : null,
                ], 401);
            }

            // 2. Get user info from Lark
            /** @var \Illuminate\Http\Client\Response $userResponse */
            $userResponse = Http::withToken($accessToken)
                ->post('https://open.larksuite.com/open-apis/authen/v1/access_token', [
                    'grant_type' => 'authorization_code',
                    'code' => $request->code,
                ]);

            if (!$userResponse->successful()) {
                Log::error('Lark user info fetch failed', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body(),
                    'json' => $userResponse->json(),
                    'code' => $request->code,
                ]);
                
                return response()->json([
                    'error' => 'Failed to fetch user info from Lark',
                    'details' => config('app.debug') ? [
                        'status' => $userResponse->status(),
                        'response' => $userResponse->json(),
                    ] : null,
                ], 401);
            }

            $larkUser = $userResponse->json('data');
            
            // Add validation for user data - Lark OAuth returns open_id, not user_id
            if (!$larkUser || !isset($larkUser['open_id'])) {
                Log::error('Invalid Lark user data', [
                    'response' => $userResponse->json(),
                ]);
                
                return response()->json([
                    'error' => 'Invalid user data from Lark',
                    'details' => config('app.debug') ? $userResponse->json() : null,
                ], 401);
            }

            // 3. Find or create user using open_id (Lark OAuth primary identifier)
            $user = User::firstOrCreate(
                ['lark_open_id' => $larkUser['open_id']],
                [
                    'email' => $larkUser['email'] ?? null,
                    'name' => $larkUser['name'] ?? 'Unknown',
                    'lark_union_id' => $larkUser['union_id'] ?? null,
                    'lark_user_id' => $larkUser['user_id'] ?? null, // May be null if not provided
                    'avatar_url' => $larkUser['avatar_url'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                ]
            );

            // Update user if email, name, or other fields changed
            if ($user->wasRecentlyCreated === false) {
                $user->update([
                    'email' => $larkUser['email'] ?? $user->email,
                    'name' => $larkUser['name'] ?? $user->name,
                    'lark_union_id' => $larkUser['union_id'] ?? $user->lark_union_id,
                    'lark_user_id' => $larkUser['user_id'] ?? $user->lark_user_id,
                    'avatar_url' => $larkUser['avatar_url'] ?? $user->avatar_url,
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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Authentication failed',
                'message' => config('app.debug') ? $e->getMessage() : 'Authentication failed',
                'details' => config('app.debug') ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
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
