<?php

namespace App\Jobs;

use App\Services\Contracts\TradingEngineInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $cryptocurrencySymbol
    ) {
        // Set queue priority for trading operations
        $this->onQueue('trading');
    }

    /**
     * Execute the job.
     */
    public function handle(TradingEngineInterface $tradingEngine): void
    {
        $jobId = $this->job ? $this->job->getJobId() : 'sync-execution';
        
        Log::info('Processing order matching for cryptocurrency', [
            'symbol' => $this->cryptocurrencySymbol,
            'job_id' => $jobId
        ]);

        try {
            $result = $tradingEngine->processOrderMatching($this->cryptocurrencySymbol);
            
            Log::info('Order matching completed', [
                'symbol' => $this->cryptocurrencySymbol,
                'matches_processed' => $result['matches_processed'] ?? 0,
                'trades_executed' => $result['trades_executed'] ?? 0
            ]);
        } catch (\Exception $e) {
            Log::error('Order matching failed', [
                'symbol' => $this->cryptocurrencySymbol,
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
        
        Log::error('Order matching job failed permanently', [
            'symbol' => $this->cryptocurrencySymbol,
            'error' => $exception->getMessage(),
            'attempts' => $attempts
        ]);
    }
}