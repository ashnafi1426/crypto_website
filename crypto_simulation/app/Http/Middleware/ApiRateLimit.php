<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    private const RATE_LIMIT_REQUESTS = 100;
    private const RATE_LIMIT_MINUTES = 60;
    private const RATE_LIMIT_KEY = 'api_rate_limit:';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get identifier (user ID if authenticated, otherwise IP address)
        $identifier = $request->user() 
            ? 'user:' . $request->user()->id 
            : 'ip:' . $request->ip();

        $key = self::RATE_LIMIT_KEY . $identifier;

        try {
            // Get current attempts
            $attempts = Cache::get($key, 0);

            // Check if rate limit exceeded
            if ($attempts >= self::RATE_LIMIT_REQUESTS) {
                return response()->json([
                    'error' => 'Rate limit exceeded',
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => self::RATE_LIMIT_MINUTES * 60
                ], 429);
            }

            // Increment attempts
            Cache::put($key, $attempts + 1, now()->addMinutes(self::RATE_LIMIT_MINUTES));

            // Add rate limit headers to response
            $response = $next($request);
            
            $remaining = max(0, self::RATE_LIMIT_REQUESTS - ($attempts + 1));
            $resetTime = now()->addMinutes(self::RATE_LIMIT_MINUTES)->timestamp;

            $response->headers->set('X-RateLimit-Limit', self::RATE_LIMIT_REQUESTS);
            $response->headers->set('X-RateLimit-Remaining', $remaining);
            $response->headers->set('X-RateLimit-Reset', $resetTime);

            return $response;

        } catch (\Exception $e) {
            // If cache fails, log error but allow request
            \Log::warning('Rate limiting cache failure: ' . $e->getMessage());
            return $next($request);
        }
    }
}
