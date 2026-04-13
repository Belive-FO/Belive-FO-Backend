<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTopManagement
{
    /**
     * Only users with the top_management role may proceed (Spatie Permission).
     *
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

        if ($request->user()->hasRole('top_management')) {
            return $next($request);
        }

        return response()->json([
            'error' => 'FORBIDDEN',
            'message' => 'Only Top Management can access this resource.',
            'status' => 403,
        ], 403);
    }
}
