<?php

namespace App\Console\Commands;

use App\Services\QueueMonitoringService;
use Illuminate\Console\Command;

class MonitorQueues extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:health-check 
                            {--json : Output in JSON format}
                            {--alert : Check for alerts only}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor queue health and performance for crypto exchange';

    /**
     * Execute the console command.
     */
    public function handle(QueueMonitoringService $queueMonitor): int
    {
        $jsonOutput = $this->option('json');
        $alertOnly = $this->option('alert');

        try {
            $statistics = $queueMonitor->getQueueStatistics();

            if ($jsonOutput) {
                $this->line(json_encode($statistics, JSON_PRETTY_PRINT));
                return 0;
            }

            if ($alertOnly) {
                $this->displayAlerts($statistics);
                return 0;
            }

            $this->displayFullReport($statistics);
            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to monitor queues: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Display alerts only
     */
    private function displayAlerts(array $statistics): void
    {
        $hasIssues = false;

        foreach ($statistics['queue_details'] as $queue) {
            if (isset($queue['error'])) {
                $this->error("❌ {$queue['queue']}: {$queue['error']}");
                $hasIssues = true;
                continue;
            }

            if ($queue['health_status'] !== 'healthy') {
                $icon = $queue['health_status'] === 'critical' ? '🚨' : '⚠️';
                $this->warn("{$icon} {$queue['queue']}: {$queue['health_status']} - {$queue['pending_jobs']} pending jobs");
                $hasIssues = true;
            }
        }

        if (!$hasIssues) {
            $this->info('✅ All queues are healthy');
        }
    }

    /**
     * Display full monitoring report
     */
    private function displayFullReport(array $statistics): void
    {
        $this->info('🔍 Crypto Exchange Queue Health Report');
        $this->line('');

        // Overall statistics
        $this->info('📊 Overall Statistics:');
        $this->line("  Total Pending Jobs: {$statistics['total_pending_jobs']}");
        $this->line("  Total Processing Jobs: {$statistics['total_processing_jobs']}");
        $this->line("  Total Failed Jobs: {$statistics['total_failed_jobs']}");
        $this->line("  Healthy Queues: {$statistics['healthy_queues']}/{$statistics['total_queues']}");
        
        $overallIcon = $statistics['overall_health'] === 'healthy' ? '✅' : '⚠️';
        $this->line("  Overall Health: {$overallIcon} {$statistics['overall_health']}");
        $this->line('');

        // Individual queue details
        $this->info('📋 Queue Details:');
        
        $headers = ['Queue', 'Status', 'Pending', 'Processing', 'Failed', 'Rate/min'];
        $rows = [];

        foreach ($statistics['queue_details'] as $queue) {
            if (isset($queue['error'])) {
                $rows[] = [
                    $queue['queue'],
                    '❌ Error',
                    'N/A',
                    'N/A', 
                    'N/A',
                    'N/A'
                ];
                continue;
            }

            $statusIcon = match($queue['health_status']) {
                'healthy' => '✅',
                'warning' => '⚠️',
                'critical' => '🚨',
                default => '❓'
            };

            $rows[] = [
                $queue['queue'],
                "{$statusIcon} {$queue['health_status']}",
                $queue['pending_jobs'],
                $queue['processing_jobs'],
                $queue['failed_jobs'],
                number_format($queue['processing_rate'], 1)
            ];
        }

        $this->table($headers, $rows);
        
        $this->line('');
        $this->info("📅 Last Updated: {$statistics['timestamp']}");
        
        // Show recommendations if there are issues
        if ($statistics['overall_health'] !== 'healthy') {
            $this->line('');
            $this->warn('💡 Recommendations:');
            $this->line('  • Check queue worker processes: php artisan queue:manage status');
            $this->line('  • Scale workers if needed: php artisan queue:manage start --workers=5');
            $this->line('  • Review failed jobs: php artisan queue:failed');
        }
    }
}
