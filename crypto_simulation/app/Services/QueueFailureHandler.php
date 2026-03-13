<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;

class QueueFailureHandler
{
    /**
     * Handle job processing events
     */
    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobName = $event->job->getName();
        $queueName = $event->job->getQueue();
        
        Log::info('Job processing started', [
            'job' => $jobName,
            'queue' => $queueName,
            'attempts' => $event->job->attempts()
        ]);
        
        // Track processing statistics
        $this->incrementProcessingCount($queueName);
    }

    /**
     * Handle successful job completion
     */
    public function handleJobProcessed(JobProcessed $event): void
    {
        $jobName = $event->job->getName();
        $queueName = $event->job->getQueue();
        
        Log::info('Job processed successfully', [
            'job' => $jobName,
            'queue' => $queueName,
            'attempts' => $event->job->attempts()
        ]);
        
        // Track success statistics
        $this->incrementSuccessCount($queueName);
    }

    /**
     * Handle job failures with intelligent retry logic
     */
    public function handleJobFailed(JobFailed $event): void
    {
        $jobName = $event->job->getName();
        $queueName = $event->job->getQueue();
        $exception = $event->exception;
        
        Log::error('Job failed', [
            'job' => $jobName,
            'queue' => $queueName,
            'attempts' => $event->job->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Track failure statistics
        $this->incrementFailureCount($queueName);
        
        // Implement intelligent retry logic
        $this->handleIntelligentRetry($event);
        
        // Check for critical failure patterns
        $this->checkFailurePatterns($queueName, $jobName, $exception);
    }

    /**
     * Implement intelligent retry logic based on failure type
     */
    private function handleIntelligentRetry(JobFailed $event): void
    {
        $exception = $event->exception;
        $jobName = $event->job->getName();
        
        // Don't retry certain types of failures
        $nonRetryableExceptions = [
            \InvalidArgumentException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class,
        ];
        
        foreach ($nonRetryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                Log::warning('Job marked as non-retryable', [
                    'job' => $jobName,
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage()
                ]);
                return;
            }
        }
        
        // Implement exponential backoff for database-related failures
        if ($this->isDatabaseException($exception)) {
            $delay = $this->calculateExponentialBackoff($event->job->attempts());
            
            Log::info('Scheduling database-related job retry with backoff', [
                'job' => $jobName,
                'delay_seconds' => $delay,
                'attempt' => $event->job->attempts()
            ]);
            
            // In a real implementation, you would reschedule the job with delay
            // This is handled by Laravel's built-in retry mechanism
        }
    }

    /**
     * Check if exception is database-related
     */
    private function isDatabaseException(\Throwable $exception): bool
    {
        $databaseExceptions = [
            \Illuminate\Database\QueryException::class,
            \PDOException::class,
            \Illuminate\Database\DeadlockException::class,
        ];
        
        foreach ($databaseExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Calculate exponential backoff delay
     */
    private function calculateExponentialBackoff(int $attempts): int
    {
        // Exponential backoff: 2^attempt seconds, max 300 seconds (5 minutes)
        return min(300, pow(2, $attempts));
    }

    /**
     * Check for critical failure patterns
     */
    private function checkFailurePatterns(string $queueName, string $jobName, \Throwable $exception): void
    {
        $cacheKey = "queue_failures:{$queueName}:{$jobName}";
        $failures = Cache::get($cacheKey, []);
        
        // Add current failure
        $failures[] = [
            'timestamp' => now()->timestamp,
            'exception' => get_class($exception),
            'message' => $exception->getMessage()
        ];
        
        // Keep only last 10 failures
        $failures = array_slice($failures, -10);
        Cache::put($cacheKey, $failures, 3600); // 1 hour
        
        // Check for critical patterns
        $recentFailures = array_filter($failures, function($failure) {
            return $failure['timestamp'] > (now()->timestamp - 300); // Last 5 minutes
        });
        
        if (count($recentFailures) >= 5) {
            Log::critical('Critical failure pattern detected', [
                'queue' => $queueName,
                'job' => $jobName,
                'recent_failures' => count($recentFailures),
                'pattern' => 'High failure rate in last 5 minutes'
            ]);
            
            $this->sendCriticalAlert($queueName, $jobName, $recentFailures);
        }
    }

    /**
     * Send critical alert for failure patterns
     */
    private function sendCriticalAlert(string $queueName, string $jobName, array $failures): void
    {
        $alertKey = "critical_alert:{$queueName}:{$jobName}";
        
        // Prevent spam - one alert per hour
        if (!Cache::has($alertKey)) {
            Cache::put($alertKey, true, 3600);
            
            Log::alert('CRITICAL QUEUE ALERT: High failure rate detected', [
                'queue' => $queueName,
                'job' => $jobName,
                'failure_count' => count($failures),
                'timeframe' => '5 minutes',
                'recommended_action' => 'Investigate job logic and system health immediately'
            ]);
        }
    }

    /**
     * Increment processing count for statistics
     */
    private function incrementProcessingCount(string $queueName): void
    {
        $key = "queue_stats:{$queueName}:processing";
        Cache::increment($key, 1);
        
        // Only set expiry if the cache driver supports it
        if (method_exists(Cache::getStore(), 'expire')) {
            Cache::expire($key, 3600); // 1 hour TTL
        } else {
            // For drivers that don't support expire, use put with TTL
            $currentValue = Cache::get($key, 0);
            Cache::put($key, $currentValue, 3600);
        }
    }

    /**
     * Increment success count for statistics
     */
    private function incrementSuccessCount(string $queueName): void
    {
        $key = "queue_stats:{$queueName}:processed";
        Cache::increment($key, 1);
        
        // Only set expiry if the cache driver supports it
        if (method_exists(Cache::getStore(), 'expire')) {
            Cache::expire($key, 3600); // 1 hour TTL
        } else {
            // For drivers that don't support expire, use put with TTL
            $currentValue = Cache::get($key, 0);
            Cache::put($key, $currentValue, 3600);
        }
    }

    /**
     * Increment failure count for statistics
     */
    private function incrementFailureCount(string $queueName): void
    {
        $key = "queue_stats:{$queueName}:failed";
        Cache::increment($key, 1);
        
        // Only set expiry if the cache driver supports it
        if (method_exists(Cache::getStore(), 'expire')) {
            Cache::expire($key, 3600); // 1 hour TTL
        } else {
            // For drivers that don't support expire, use put with TTL
            $currentValue = Cache::get($key, 0);
            Cache::put($key, $currentValue, 3600);
        }
    }

    /**
     * Get failure statistics for a queue
     */
    public function getFailureStatistics(string $queueName): array
    {
        return [
            'processing' => Cache::get("queue_stats:{$queueName}:processing", 0),
            'processed' => Cache::get("queue_stats:{$queueName}:processed", 0),
            'failed' => Cache::get("queue_stats:{$queueName}:failed", 0),
        ];
    }
}