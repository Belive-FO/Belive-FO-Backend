<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Paths allowed for any authenticated user regardless of account status
     * (pending verification, rejected, etc.).
     *
     * @var list<string>
     */
    protected array $allowedPathPatterns = [
        'api/user',
        'api/auth/logout',
        'api/auth/resubmit',
    ];

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return response()->json([
                'error' => 'UNAUTHENTICATED',
                'message' => 'User is not logged in or token is invalid.',
                'status' => 401,
            ], 401);
        }

        $user = $request->user();
        $status = $user->status;

        if ($this->isAllowedPath($request)) {
            return $next($request);
        }

        $errorMap = [
            'verifying' => [
                'error' => 'ACCOUNT_VERIFICATION_PENDING',
                'message' => 'Your account is under verification.',
                'status' => 403,
                'accessStatus' => 'pending',
            ],
            'rejected' => [
                'error' => 'ACCOUNT_REJECTED',
                'message' => 'Your account has been rejected.',
                'status' => 403,
                'accessStatus' => 'rejected',
            ],
            'deactivated' => [
                'error' => 'ACCOUNT_DEACTIVATED',
                'message' => 'Your account has been deactivated.',
                'status' => 403,
                'accessStatus' => 'deactivated',
            ],
        ];

        if (isset($errorMap[$status])) {
            return response()->json([
                ...$errorMap[$status],
                'canProceed' => false,
            ], 403);
        }

        return $next($request);
    }

    protected function isAllowedPath(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->allowedPathPatterns as $pattern) {
            if ($path === $pattern || $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
