<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class QueueWorkerManager extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'queue:manage 
                            {action : start, stop, restart, status}
                            {--queue=* : Specific queues to manage}
                            {--workers=3 : Number of workers per queue}';

    /**
     * The console command description.
     */
    protected $description = 'Manage queue workers for crypto exchange trading system';

    /**
     * Default queues for the crypto exchange system
     */
    private array $defaultQueues = ['trading', 'prices', 'default'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $queues = $this->option('queue') ?: $this->defaultQueues;
        $workers = (int) $this->option('workers');

        return match ($action) {
            'start' => $this->startWorkers($queues, $workers),
            'stop' => $this->stopWorkers(),
            'restart' => $this->restartWorkers($queues, $workers),
            'status' => $this->showStatus(),
            default => $this->error("Invalid action: {$action}. Use: start, stop, restart, status")
        };
    }

    /**
     * Start queue workers
     */
    private function startWorkers(array $queues, int $workers): int
    {
        $this->info('Starting queue workers for crypto exchange...');

        foreach ($queues as $queue) {
            for ($i = 1; $i <= $workers; $i++) {
                $this->info("Starting worker {$i} for queue: {$queue}");
                
                // Start worker in background
                $command = "php artisan queue:work redis --queue={$queue} --sleep=3 --tries=3 --max-time=3600 --daemon";
                
                if (PHP_OS_FAMILY === 'Windows') {
                    popen("start /B {$command}", 'r');
                } else {
                    exec("{$command} > /dev/null 2>&1 &");
                }
            }
        }

        $this->info('Queue workers started successfully!');
        $this->info('Monitor workers with: php artisan queue:manage status');
        
        Log::info('Queue workers started', [
            'queues' => $queues,
            'workers_per_queue' => $workers
        ]);

        return 0;
    }

    /**
     * Stop all queue workers
     */
    private function stopWorkers(): int
    {
        $this->info('Stopping all queue workers...');
        
        Artisan::call('queue:restart');
        
        $this->info('Queue workers stopped successfully!');
        
        Log::info('Queue workers stopped');

        return 0;
    }

    /**
     * Restart queue workers
     */
    private function restartWorkers(array $queues, int $workers): int
    {
        $this->info('Restarting queue workers...');
        
        $this->stopWorkers();
        sleep(2); // Give workers time to stop
        $this->startWorkers($queues, $workers);
        
        return 0;
    }

    /**
     * Show worker status
     */
    private function showStatus(): int
    {
        $this->info('Queue Worker Status:');
        $this->line('');
        
        // Show queue statistics
        Artisan::call('queue:monitor', [
            'queues' => implode(',', $this->defaultQueues)
        ]);
        
        $this->line('');
        $this->info('To view detailed queue information:');
        $this->line('- php artisan queue:monitor trading,prices,default');
        $this->line('- php artisan horizon:status (if using Horizon)');
        
        return 0;
    }
}
