<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogging
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Log request details
        $this->logRequest($request);

        $response = $next($request);

        // Log response details
        $this->logResponse($request, $response, $startTime);

        return $response;
    }

    /**
     * Log incoming request details.
     */
    private function logRequest(Request $request): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ];

        // Don't log sensitive data in request body
        if (!$this->isSensitiveEndpoint($request)) {
            $logData['request_body'] = $request->all();
        }

        Log::info('API Request', $logData);
    }

    /**
     * Log response details.
     */
    private function logResponse(Request $request, Response $response, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

        $logData = [
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $request->user()?->id,
        ];

        // Log response body for errors
        if ($response->getStatusCode() >= 400) {
            $content = $response->getContent();
            if ($content && $this->isJson($content)) {
                $logData['response_body'] = json_decode($content, true);
            }
        }

        // Log slow requests
        if ($duration > 1000) { // > 1 second
            Log::warning('Slow API Response', $logData);
        } else {
            Log::info('API Response', $logData);
        }
    }

    /**
     * Check if endpoint handles sensitive data.
     */
    private function isSensitiveEndpoint(Request $request): bool
    {
        $sensitiveEndpoints = [
            '/api/auth/login',
            '/api/auth/register',
            '/api/auth/password/reset',
        ];

        $path = $request->path();
        
        foreach ($sensitiveEndpoints as $endpoint) {
            if (str_contains($path, trim($endpoint, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize headers to remove sensitive information.
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    /**
     * Check if content is JSON.
     */
    private function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
