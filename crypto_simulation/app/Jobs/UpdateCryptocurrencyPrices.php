<?php

namespace App\Jobs;

use App\Services\Contracts\PriceSimulatorInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateCryptocurrencyPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Set queue for price updates
        $this->onQueue('prices');
    }

    /**
     * Execute the job.
     */
    public function handle(PriceSimulatorInterface $priceSimulator): void
    {
        $jobId = $this->job ? $this->job->getJobId() : 'sync-execution';
        
        Log::info('Starting cryptocurrency price update job', [
            'job_id' => $jobId
        ]);

        try {
            $result = $priceSimulator->updatePrices();
            
            Log::info('Price update completed', [
                'cryptocurrencies_updated' => $result['updated_count'] ?? 0,
                'volatility_alerts' => $result['volatility_alerts'] ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error('Price update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $attempts = $this->job ? $this->job->attempts() : 1;
        
        Log::error('Price update job failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $attempts
        ]);
    }
}