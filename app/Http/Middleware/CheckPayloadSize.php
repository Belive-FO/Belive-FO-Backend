<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPayloadSize
{
    protected const MAX_BYTES = 10 * 1024 * 1024; // 10 MB

    /**
     * Reject requests with a body exceeding the allowed size before further processing.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = $request->server('CONTENT_LENGTH');

        if ($contentLength && (int) $contentLength > self::MAX_BYTES) {
            return response()->json([
                'error'   => 'PAYLOAD_TOO_LARGE',
                'message' => 'Request payload exceeds the maximum allowed size of 10MB.',
                'status'  => 413,
            ], 413);
        }

        return $next($request);
    }
}
