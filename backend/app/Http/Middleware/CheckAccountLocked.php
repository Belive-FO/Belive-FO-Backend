<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountLocked
{
    protected const MAX_ATTEMPTS = 5;
    protected const LOCKOUT_DURATION = 15; // minutes

    /**
     * Block authenticated requests when the account is temporarily locked.
     * Used on protected routes as a gate for brute-force-locked accounts.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $lockKey = "account_locked:{$user->id}";

            if (Cache::has($lockKey)) {
                $remainingTime = Cache::get($lockKey . ':expires_at') - now()->timestamp;

                return response()->json([
                    'error'            => 'ACCOUNT_LOCKED',
                    'message'          => 'Your account has been temporarily locked due to too many failed login attempts. Please try again later.',
                    'status'           => 423,
                    'lockoutDuration'  => self::LOCKOUT_DURATION,
                    'remainingSeconds' => max(0, $remainingTime),
                ], 423);
            }
        }

        return $next($request);
    }

    /**
     * Record a failed login attempt. Locks the account after MAX_ATTEMPTS.
     */
    public static function recordFailedAttempt(int $userId): void
    {
        $attemptKey = "login_attempts:{$userId}";
        $lockKey    = "account_locked:{$userId}";

        $attempts = Cache::get($attemptKey, 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::put($lockKey, true, now()->addMinutes(self::LOCKOUT_DURATION));
            Cache::put($lockKey . ':expires_at', now()->addMinutes(self::LOCKOUT_DURATION)->timestamp, now()->addMinutes(self::LOCKOUT_DURATION));
            Cache::forget($attemptKey);
        } else {
            Cache::put($attemptKey, $attempts, now()->addMinutes(self::LOCKOUT_DURATION));
        }
    }

    /**
     * Clear failed login attempts on successful login.
     */
    public static function clearFailedAttempts(int $userId): void
    {
        Cache::forget("login_attempts:{$userId}");
        Cache::forget("account_locked:{$userId}");
        Cache::forget("account_locked:{$userId}:expires_at");
    }
}
