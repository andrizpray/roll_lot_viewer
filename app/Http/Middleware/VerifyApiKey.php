<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiKey
{
    /**
     * Verify that the request contains a valid API key.
     *
     * Checks in order: X-API-Key header, then ?api_key query param.
     * If APP_ENV is 'local' and no API_KEY is configured, skip check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('app.api_key');

        // If no API key configured, skip auth entirely.
        // This makes API key an opt-in security layer for internal apps.
        if (empty($configuredKey)) {
            return $next($request);
        }

        // Check header first, then query param
        $providedKey = $request->header('X-API-Key')
            ?? $request->query('api_key');

        if (empty($providedKey) || !hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'error' => 'Invalid or missing API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
