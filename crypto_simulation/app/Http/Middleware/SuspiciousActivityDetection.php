<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SuspiciousActivityDetection
{
    private const SUSPICIOUS_THRESHOLD = 50; // requests per minute
    private const BLOCK_DURATION = 60; // minutes
    private const SUSPICIOUS_KEY = 'suspicious_activity:';
    private const BLOCKED_KEY = 'blocked_ip:';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Check if IP is already blocked
        if ($this->isBlocked($ip)) {
            Log::warning('Blocked IP attempted access', [
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'user_agent' => $userAgent
            ]);

            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your IP has been temporarily blocked due to suspicious activity.'
            ], 403);
        }

        // Detect suspicious patterns
        if ($this->detectSuspiciousActivity($request)) {
            $this->blockIp($ip);
            
            Log::alert('Suspicious activity detected and IP blocked', [
                'ip' => $ip,
                'url' => $request->fullUrl(),
                'user_agent' => $userAgent,
                'user_id' => $request->user()?->id
            ]);

            return response()->json([
                'error' => 'Suspicious activity detected',
                'message' => 'Your access has been temporarily restricted.'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Check if IP is blocked.
     */
    private function isBlocked(string $ip): bool
    {
        try {
            return Cache::has(self::BLOCKED_KEY . $ip);
        } catch (\Exception $e) {
            Log::warning('Cache error in suspicious activity detection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Block IP address.
     */
    private function blockIp(string $ip): void
    {
        try {
            Cache::put(self::BLOCKED_KEY . $ip, true, now()->addMinutes(self::BLOCK_DURATION));
        } catch (\Exception $e) {
            Log::warning('Cache error when blocking IP: ' . $e->getMessage());
        }
    }

    /**
     * Detect suspicious activity patterns.
     */
    private function detectSuspiciousActivity(Request $request): bool
    {
        $ip = $request->ip();
        $key = self::SUSPICIOUS_KEY . $ip;

        try {
            // Track request frequency
            $requests = Cache::get($key, []);
            $now = now();
            
            // Remove old requests (older than 1 minute)
            $requests = array_filter($requests, function ($timestamp) use ($now) {
                return $now->diffInSeconds($timestamp) < 60;
            });

            // Add current request
            $requests[] = $now;
            Cache::put($key, $requests, now()->addMinutes(5));

            // Check if exceeds threshold
            if (count($requests) > self::SUSPICIOUS_THRESHOLD) {
                return true;
            }

            // Check for other suspicious patterns
            return $this->checkSuspiciousPatterns($request);

        } catch (\Exception $e) {
            Log::warning('Error in suspicious activity detection: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for additional suspicious patterns.
     */
    private function checkSuspiciousPatterns(Request $request): bool
    {
        $userAgent = $request->userAgent();
        $url = $request->fullUrl();

        // Suspicious user agents (but allow legitimate browsers and development tools)
        $suspiciousAgents = [
            'bot', 'crawler', 'spider', 'scraper', 'wget', 'python', 'java'
        ];

        // Allow common development and testing tools
        $allowedAgents = [
            'mozilla', 'chrome', 'firefox', 'safari', 'edge', 'postman', 'insomnia'
        ];

        // Check if user agent contains allowed patterns first
        foreach ($allowedAgents as $allowed) {
            if (stripos($userAgent, $allowed) !== false) {
                return false; // Allow legitimate browsers and tools
            }
        }

        // Only block if it's a suspicious agent and not a legitimate tool
        foreach ($suspiciousAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                Log::info('Suspicious user agent detected', [
                    'ip' => $request->ip(),
                    'user_agent' => $userAgent
                ]);
                return true;
            }
        }

        // Suspicious URL patterns
        $suspiciousPatterns = [
            'wp-admin', 'phpmyadmin', '.env', 'config', 'backup'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                Log::info('Suspicious URL pattern detected', [
                    'ip' => $request->ip(),
                    'url' => $url
                ]);
                return true;
            }
        }

        // Check for SQL injection attempts in query parameters
        $queryString = $request->getQueryString();
        if ($queryString && $this->containsSqlInjection($queryString)) {
            Log::warning('SQL injection attempt detected', [
                'ip' => $request->ip(),
                'query' => $queryString
            ]);
            return true;
        }

        return false;
    }

    /**
     * Check for SQL injection patterns.
     */
    private function containsSqlInjection(string $input): bool
    {
        $sqlPatterns = [
            '/(\s*(union|select|insert|update|delete|drop|create|alter)\s+)/i',
            '/(\s*(or|and)\s+\d+\s*=\s*\d+)/i',
            '/(\s*;\s*(drop|delete|truncate)\s+)/i',
            '/(\'|\"|`).*(\'|\"|`)/i'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
