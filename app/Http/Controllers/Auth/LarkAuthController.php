<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckAccountLocked;
use App\Http\Resources\UserOnboardingResource;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Services\UserOnboardingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class LarkAuthController extends Controller
{
    use ApiResponse;

    /**
     * Handle Lark OAuth callback (POST-based: frontend sends the code).
     *
     * @response 200 { "message": "Login successful", "data": { "user": {}, "token": "1|abc..." } }
     * @response 401 { "error": "LARK_AUTH_FAILED", "message": "...", "status": 401 }
     * @response 500 { "error": "INTERNAL_SERVER_ERROR", "message": "...", "status": 500 }
     */
    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            // 1. Exchange code for tenant access token
            /** @var \Illuminate\Http\Client\Response $tokenResponse */
            $tokenResponse = Http::post('https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal', [
                'app_id' => config('services.lark.app_id'),
                'app_secret' => config('services.lark.app_secret'),
            ]);

            if (! $tokenResponse->successful()) {
                Log::error('Lark token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body(),
                ]);

                return $this->error(
                    'LARK_AUTH_FAILED',
                    'Failed to authenticate with Lark.',
                    401,
                    config('app.debug') ? ['lark_response' => $tokenResponse->json()] : null
                );
            }

            $accessToken = $tokenResponse->json('tenant_access_token');

            if (! $accessToken) {
                Log::error('Lark tenant access token missing', ['response' => $tokenResponse->json()]);

                return $this->error('LARK_AUTH_FAILED', 'Failed to get access token from Lark.', 401);
            }

            // 2. Get user info from Lark
            /** @var \Illuminate\Http\Client\Response $userResponse */
            $userResponse = Http::withToken($accessToken)
                ->post('https://open.larksuite.com/open-apis/authen/v1/access_token', [
                    'grant_type' => 'authorization_code',
                    'code' => $request->code,
                ]);

            if (! $userResponse->successful()) {
                Log::error('Lark user info fetch failed', [
                    'status' => $userResponse->status(),
                    'code' => $request->code,
                ]);

                return $this->error('LARK_AUTH_FAILED', 'Failed to fetch user info from Lark.', 401);
            }

            $larkUser = $userResponse->json('data');

            if (! $larkUser || ! isset($larkUser['open_id'])) {
                Log::error('Invalid Lark user data', ['response' => $userResponse->json()]);

                return $this->error('LARK_AUTH_FAILED', 'Invalid user data received from Lark.', 401);
            }

            // 3. Find or create user
            $user = User::firstOrCreate(
                ['lark_open_id' => $larkUser['open_id']],
                [
                    'email' => $larkUser['email'] ?? null,
                    'name' => $larkUser['name'] ?? 'Unknown',
                    'lark_user_id' => $larkUser['user_id'] ?? null,
                    'avatar_url' => $larkUser['avatar_url'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'verifying',
                ]
            );

            if ($user->wasRecentlyCreated) {
                $user->assignRole('staff');
                UserOnboardingService::createForUser($user->id);
            } else {
                $user->update([
                    'email' => $larkUser['email'] ?? $user->email,
                    'name' => $larkUser['name'] ?? $user->name,
                    'lark_user_id' => $larkUser['user_id'] ?? $user->lark_user_id,
                    'avatar_url' => $larkUser['avatar_url'] ?? $user->avatar_url,
                ]);

                if ($user->status === 'rejected') {
                    try {
                        UserOnboardingService::handleResubmission($user->fresh());
                        $user->refresh();
                    } catch (\Throwable $e) {
                        Log::warning('Onboarding resubmission on Lark login failed', [
                            'user_id' => $user->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $user = $user->fresh()->load('roles');

            if ($user->status === 'deactivated') {
                return $this->error(
                    'ACCOUNT_DEACTIVATED',
                    'This account has been deactivated.',
                    403
                );
            }

            // 4. Create Bearer token (no session — Sanctum token only)
            $user->last_login_at = now();
            $user->save();
            $user = $user->fresh()->load('roles');

            $token = $user->createToken('belive-fo-lark')->plainTextToken;

            $payload = $this->buildAuthPayload($user);
            $payload['token'] = $token;

            return $this->success($payload, 'Login successful.');
        } catch (\Exception $e) {
            Log::error('Lark authentication error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->error(
                'INTERNAL_SERVER_ERROR',
                config('app.debug') ? $e->getMessage() : 'Authentication failed.',
                500
            );
        }
    }

    /**
     * Login with email and password.
     *
     * @response 200 { "message": "Login successful.", "data": { "user": {}, "token": "1|abc..." } }
     * @response 401 { "error": "INVALID_CREDENTIALS", "message": "Invalid credentials.", "status": 401 }
     * @response 423 { "error": "ACCOUNT_LOCKED", "message": "...", "status": 423, "remainingSeconds": 300 }
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Check brute-force lock before attempting
        if ($user) {
            $lockKey = "account_locked:{$user->id}";
            if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
                $remainingTime = \Illuminate\Support\Facades\Cache::get($lockKey.':expires_at') - now()->timestamp;

                return $this->error(
                    'ACCOUNT_LOCKED',
                    'Your account has been temporarily locked due to too many failed login attempts. Please try again later.',
                    423,
                    ['remainingSeconds' => max(0, $remainingTime)]
                );
            }
        }

        if ($user && $user->status === 'deactivated') {
            return $this->error('ACCOUNT_DEACTIVATED', 'This account has been deactivated.', 403);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            if ($user) {
                CheckAccountLocked::recordFailedAttempt($user->id);
            }

            return $this->error('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
        }

        $authenticatedUser = $user;
        CheckAccountLocked::clearFailedAttempts($authenticatedUser->id);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $authenticatedUser->last_login_at = now();
        $authenticatedUser->save();
        $authenticatedUser = $authenticatedUser->fresh()->load('roles');

        $token = $authenticatedUser->createToken('belive-fo')->plainTextToken;

        $payload = $this->buildAuthPayload($authenticatedUser);
        $payload['token'] = $token;

        return $this->success($payload, 'Login successful.');
    }

    /**
     * Logout — dual-mode: revokes Bearer token and/or invalidates session.
     *
     * @response 200 { "message": "Logged out successfully." }
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $token = $user->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get the currently authenticated user.
     *
     * @response 200 { "message": "Operation successful.", "data": { "user": {} } }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles');

        return $this->success($this->buildAuthPayload($user));
    }

    /**
     * Re-submit onboarding after rejection (authenticated user).
     */
    public function resubmit(Request $request): JsonResponse
    {
        try {
            $onboarding = UserOnboardingService::handleResubmission($request->user());

            return $this->success(
                ['onboarding' => new UserOnboardingResource($onboarding->load('user'))],
                'Application resubmitted successfully.'
            );
        } catch (\Throwable $e) {
            return $this->error('RESUBMISSION_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * @return array{user: array<string, mixed>, accessStatus: string, rejectionReason: ?string, onboarding: mixed}
     */
    private function buildAuthPayload(User $user): array
    {
        $userForResponse = $user->toArray();
        $userForResponse['roles'] = $user->getRoleNames()->toArray();

        $accessStatus = 'granted';
        $rejectionReason = null;
        $onboarding = null;

        if ($user->status === 'verifying') {
            $accessStatus = 'pending';
            $latest = UserOnboarding::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();
            if ($latest) {
                $onboarding = (new UserOnboardingResource($latest->loadMissing('user')))->resolve(request());
            }
        } elseif ($user->status === 'rejected') {
            $accessStatus = 'rejected';
            $latest = UserOnboarding::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();
            $rejectionReason = $latest?->rejection_reason ?? 'Account rejected.';
            if ($latest) {
                $onboarding = (new UserOnboardingResource($latest->loadMissing('user')))->resolve(request());
            }
        } elseif ($user->status === 'deactivated') {
            $accessStatus = 'deactivated';
        }

        return [
            'user' => $userForResponse,
            'accessStatus' => $accessStatus,
            'rejectionReason' => $rejectionReason,
            'onboarding' => $onboarding,
        ];
    }
}
