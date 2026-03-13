<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data
        $input = $request->all();
        $sanitized = $this->sanitizeInput($input);
        
        // Replace request input with sanitized data
        $request->replace($sanitized);

        return $next($request);
    }

    /**
     * Recursively sanitize input data.
     */
    private function sanitizeInput(array $input): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_string($value)) {
                // Remove potential SQL injection patterns
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize string input.
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Remove potential XSS patterns
        $value = strip_tags($value);
        
        // Remove potential SQL injection patterns
        $dangerous_patterns = [
            '/(\s*(union|select|insert|update|delete|drop|create|alter|exec|execute)\s+)/i',
            '/(\s*(or|and)\s+\d+\s*=\s*\d+)/i',
            '/(\s*;\s*(drop|delete|truncate)\s+)/i',
            '/(script|javascript|vbscript|onload|onerror|onclick)/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }
        
        // Trim whitespace
        return trim($value);
    }
}
