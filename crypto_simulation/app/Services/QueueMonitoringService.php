<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class QueueMonitoringService
{
    /**
     * Monitor queue health and performance
     */
    public function monitorQueues(): array
    {
        $queues = ['trading', 'prices', 'default'];
        $metrics = [];

        foreach ($queues as $queue) {
            $metrics[$queue] = $this->getQueueMetrics($queue);
        }

        // Check for alerts
        $this->checkQueueAlerts($metrics);

        return $metrics;
    }

    /**
     * Get metrics for a specific queue
     */
    private function getQueueMetrics(string $queue): array
    {
        try {
            $redis = Redis::connection();
            
            // Get queue length
            $queueLength = $redis->llen("queues:{$queue}");
            
            // Get processing count (approximate)
            $processingCount = $redis->llen("queues:{$queue}:processing");
            
            // Get failed jobs count
            $failedCount = $redis->llen("queues:{$queue}:failed");
            
            // Calculate processing rate (jobs per minute)
            $processingRate = $this->calculateProcessingRate($queue);
            
            return [
                'queue' => $queue,
                'pending_jobs' => $queueLength,
                'processing_jobs' => $processingCount,
                'failed_jobs' => $failedCount,
                'processing_rate' => $processingRate,
                'health_status' => $this->determineHealthStatus($queueLength, $processingRate),
                'last_checked' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get metrics for queue: {$queue}", [
                'error' => $e->getMessage()
            ]);
            
            return [
                'queue' => $queue,
                'error' => 'Failed to retrieve metrics',
                'health_status' => 'error',
                'last_checked' => now()->toISOString()
            ];
        }
    }

    /**
     * Calculate processing rate for a queue
     */
    private function calculateProcessingRate(string $queue): float
    {
        $cacheKey = "queue_metrics:{$queue}:processed_count";
        $currentCount = $this->getProcessedJobsCount($queue);
        $previousCount = Cache::get($cacheKey, $currentCount);
        
        // Store current count for next calculation
        Cache::put($cacheKey, $currentCount, 300); // 5 minutes
        
        // Calculate rate (jobs per minute)
        $rate = max(0, $currentCount - $previousCount);
        
        return $rate;
    }

    /**
     * Get total processed jobs count for a queue
     */
    private function getProcessedJobsCount(string $queue): int
    {
        try {
            $redis = Redis::connection();
            $count = $redis->get("queue_stats:{$queue}:processed") ?? 0;
            return (int) $count;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Determine health status based on metrics
     */
    private function determineHealthStatus(int $queueLength, float $processingRate): string
    {
        // Critical: Queue is backing up significantly
        if ($queueLength > 1000) {
            return 'critical';
        }
        
        // Warning: Queue is growing or processing slowly
        if ($queueLength > 100 || $processingRate < 1) {
            return 'warning';
        }
        
        // Healthy: Normal operation
        return 'healthy';
    }

    /**
     * Check for queue alerts and log warnings
     */
    private function checkQueueAlerts(array $metrics): void
    {
        foreach ($metrics as $queueMetrics) {
            if (isset($queueMetrics['error'])) {
                continue;
            }

            $queue = $queueMetrics['queue'];
            $status = $queueMetrics['health_status'];
            $pendingJobs = $queueMetrics['pending_jobs'];

            switch ($status) {
                case 'critical':
                    Log::critical("Queue {$queue} is in critical state", $queueMetrics);
                    $this->sendAlert($queue, 'critical', $queueMetrics);
                    break;
                    
                case 'warning':
                    Log::warning("Queue {$queue} performance degraded", $queueMetrics);
                    break;
                    
                case 'healthy':
                    // Log info only for trading queue to track performance
                    if ($queue === 'trading') {
                        Log::info("Trading queue healthy", $queueMetrics);
                    }
                    break;
            }
        }
    }

    /**
     * Send alert for critical queue issues
     */
    private function sendAlert(string $queue, string $level, array $metrics): void
    {
        // In a production system, this would send notifications
        // via email, Slack, or other alerting systems
        
        $alertKey = "queue_alert:{$queue}:{$level}";
        
        // Prevent spam by limiting alerts to once per hour
        if (!Cache::has($alertKey)) {
            Cache::put($alertKey, true, 3600); // 1 hour
            
            Log::alert("QUEUE ALERT: {$queue} queue requires immediate attention", [
                'level' => $level,
                'metrics' => $metrics,
                'recommended_action' => $this->getRecommendedAction($level, $metrics)
            ]);
        }
    }

    /**
     * Get recommended action for queue issues
     */
    private function getRecommendedAction(string $level, array $metrics): string
    {
        if ($level === 'critical') {
            return 'Scale up queue workers immediately or investigate processing bottlenecks';
        }
        
        return 'Monitor queue performance and consider scaling workers';
    }

    /**
     * Get queue statistics summary
     */
    public function getQueueStatistics(): array
    {
        $metrics = $this->monitorQueues();
        
        $totalPending = array_sum(array_column($metrics, 'pending_jobs'));
        $totalProcessing = array_sum(array_column($metrics, 'processing_jobs'));
        $totalFailed = array_sum(array_column($metrics, 'failed_jobs'));
        
        $healthyQueues = count(array_filter($metrics, fn($m) => ($m['health_status'] ?? '') === 'healthy'));
        $totalQueues = count($metrics);
        
        return [
            'total_pending_jobs' => $totalPending,
            'total_processing_jobs' => $totalProcessing,
            'total_failed_jobs' => $totalFailed,
            'healthy_queues' => $healthyQueues,
            'total_queues' => $totalQueues,
            'overall_health' => $healthyQueues === $totalQueues ? 'healthy' : 'degraded',
            'queue_details' => $metrics,
            'timestamp' => now()->toISOString()
        ];
    }
}