<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA mode configuration
        $middleware->statefulApi();

        // Spatie Permission (Laravel 12 does not auto-register these)
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check.top_management' => \App\Http\Middleware\CheckTopManagement::class,
            'check.account_status' => \App\Http\Middleware\CheckAccountStatus::class,
            'check.account_locked' => \App\Http\Middleware\CheckAccountLocked::class,
        ]);

        // Reject oversized request bodies early (10MB cap)
        $middleware->append(\App\Http\Middleware\CheckPayloadSize::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // 401 — Unauthenticated (prevents HTML redirect to /login)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'UNAUTHENTICATED',
                    'message' => 'You must be authenticated to access this resource.',
                    'status' => 401,
                ], 401);
            }
        });

        // 422 — Validation errors
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'status' => 422,
                    'fields' => $e->errors(),
                ], 422);
            }
        });

        // 404 — Model not found
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'status' => 404,
                ], 404);
            }
        });

        // 429 — Rate limit exceeded
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please slow down.',
                    'status' => 429,
                    'retryAfter' => (int) $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });

        // 403 — Permission denied
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'PERMISSION_DENIED',
                    'message' => 'You do not have permission to perform this action.',
                    'status' => 403,
                ], 403);
            }
        });

        // 409/423/500 — Database errors (duplicate, FK violation, generic)
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $errorCode = $e->errorInfo[1] ?? null;

                // Duplicate entry (MySQL 1062 / Postgres 23505)
                if ($errorCode === 1062 || str_contains($e->getMessage(), '23505')) {
                    return response()->json([
                        'error' => 'DUPLICATE_VALUE',
                        'message' => 'A record with this value already exists.',
                        'status' => 409,
                    ], 409);
                }

                // Foreign key violation (MySQL 1451/1452 / Postgres 23503)
                if (in_array($errorCode, [1451, 1452]) || str_contains($e->getMessage(), '23503')) {
                    return response()->json([
                        'error' => 'RESOURCE_LOCKED',
                        'message' => 'This resource is referenced by other records and cannot be modified.',
                        'status' => 423,
                    ], 423);
                }

                Log::error('Database query failed', [
                    'message' => $e->getMessage(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ]);

                return response()->json([
                    'error' => 'DATABASE_ERROR',
                    'message' => 'A database error occurred.',
                    'status' => 500,
                ], 500);
            }
        });

        // Catch-all — unexpected exceptions
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

                return response()->json([
                    'error' => 'INTERNAL_SERVER_ERROR',
                    'message' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
                    'status' => $status,
                ], $status);
            }
        });

    })->create();
