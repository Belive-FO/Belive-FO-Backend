<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrustedBffMiddleware
 * 
 * Validates that requests come from the trusted Next.js BFF (Backend for Frontend).
 * 
 * In a Supabase-first architecture:
 * - Next.js BFF validates Supabase JWT tokens
 * - Next.js extracts user identity from JWT
 * - Next.js calls Laravel with X-User-ID and X-Internal-Key headers
 * - This middleware validates the internal key and sets user context
 * 
 * Laravel trusts the BFF's identity assertion because:
 * 1. The BFF has validated the Supabase JWT
 * 2. The internal key is a shared secret between Next.js and Laravel
 * 3. Laravel doesn't need to re-authenticate - it focuses on business logic
 */
class TrustedBffMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Validate internal key (shared secret between Next.js and Laravel)
        $internalKey = $request->header('X-Internal-Key');
        $expectedKey = config('services.bff.secret');
        
        if (!$internalKey || !hash_equals($expectedKey, $internalKey)) {
            abort(403, 'Untrusted caller. Missing or invalid X-Internal-Key header.');
        }
        
        // 2. Extract user ID from BFF header (already validated by Next.js via Supabase JWT)
        $userId = $request->header('X-User-ID');
        
        if (!$userId || !is_numeric($userId)) {
            abort(400, 'Missing or invalid X-User-ID header.');
        }
        
        // 3. Set user context for Laravel (without re-authentication)
        // This allows Laravel to use auth()->id() and similar helpers
        $request->merge(['user_id' => (int) $userId]);
        
        // Optionally, you can set the authenticated user if you have a User model
        // This is useful if your code uses auth()->user()
        // $user = \App\Models\User::find($userId);
        // if ($user) {
        //     auth()->setUser($user);
        // }
        
        return $next($request);
    }
}


