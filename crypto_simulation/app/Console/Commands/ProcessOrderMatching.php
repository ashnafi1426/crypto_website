<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderMatching as ProcessOrderMatchingJob;
use App\Models\Cryptocurrency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessOrderMatching extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trading:process-matches 
                            {symbol? : Specific cryptocurrency symbol to process}
                            {--all : Process all active cryptocurrencies}
                            {--async : Queue jobs asynchronously}';

    /**
     * The console command description.
     */
    protected $description = 'Process order matching for cryptocurrencies';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $symbol = $this->argument('symbol');
        $processAll = $this->option('all');
        $async = $this->option('async');

        if (!$symbol && !$processAll) {
            $this->error('Please specify a cryptocurrency symbol or use --all flag');
            return 1;
        }

        $cryptocurrencies = $this->getCryptocurrencies($symbol, $processAll);

        if ($cryptocurrencies->isEmpty()) {
            $this->warn('No cryptocurrencies found to process');
            return 0;
        }

        $this->info('Processing order matching...');
        $this->line('');

        $processed = 0;
        foreach ($cryptocurrencies as $crypto) {
            $this->info("Processing {$crypto->symbol} ({$crypto->name})...");
            
            try {
                if ($async) {
                    // Queue the job for asynchronous processing
                    ProcessOrderMatchingJob::dispatch($crypto->symbol);
                    $this->line("  → Queued for processing");
                } else {
                    // Process synchronously
                    $job = new ProcessOrderMatchingJob($crypto->symbol);
                    $job->handle(app(\App\Services\Contracts\TradingEngineInterface::class));
                    $this->line("  → Processed synchronously");
                }
                
                $processed++;
            } catch (\Exception $e) {
                $this->error("  → Failed: {$e->getMessage()}");
                Log::error('Order matching command failed', [
                    'symbol' => $crypto->symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->line('');
        $this->info("Completed processing {$processed} cryptocurrencies");
        
        if ($async) {
            $this->info('Jobs have been queued. Monitor with: php artisan queue:manage status');
        }

        return 0;
    }

    /**
     * Get cryptocurrencies to process
     */
    private function getCryptocurrencies(?string $symbol, bool $processAll)
    {
        if ($processAll) {
            return Cryptocurrency::where('is_active', true)->get();
        }

        if ($symbol) {
            $crypto = Cryptocurrency::where('symbol', strtoupper($symbol))
                                  ->where('is_active', true)
                                  ->first();
            
            return $crypto ? collect([$crypto]) : collect();
        }

        return collect();
    }
}
