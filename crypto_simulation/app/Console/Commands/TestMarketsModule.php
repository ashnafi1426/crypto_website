<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cryptocurrency;
use App\Services\MarketService;

class TestMarketsModule extends Command
{
    protected $signature = 'test:markets';
    protected $description = 'Test the Markets module implementation';

    public function handle()
    {
        $this->info('=== Testing Markets Module Implementation ===');
        $this->newLine();

        // Test 1: Check cryptocurrency data
        $this->info('1. Testing Cryptocurrency Data:');
        $cryptos = Cryptocurrency::active()->get();
        foreach ($cryptos as $crypto) {
            $this->line("   - {$crypto->symbol}: {$crypto->name}");
            $this->line("     Price: \${$crypto->current_price}");
            $this->line("     Market Cap: \${$crypto->market_cap}");
            $this->line("     24h Volume: \${$crypto->volume_24h}");
            $this->line("     24h Change: {$crypto->price_change_percentage_24h}%");
            $this->newLine();
        }

        // Test 2: Test MarketService
        $this->info('2. Testing MarketService:');
        $marketService = new MarketService();

        try {
            $markets = $marketService->getAllMarkets();
            $this->info("   ✓ Successfully retrieved " . count($markets) . " markets");
            
            if (!empty($markets)) {
                $btc = array_filter($markets, fn($m) => $m['symbol'] === 'BTC');
                if (!empty($btc)) {
                    $btc = array_values($btc)[0];
                    $this->info("   ✓ BTC Market Data:");
                    $this->line("     - Pair: {$btc['pair']}");
                    $this->line("     - Price: \${$btc['price']}");
                    $this->line("     - Market Cap: \${$btc['market_cap']}");
                    $this->line("     - 24h Volume: \${$btc['volume_24h']}");
                    $this->line("     - 24h Change: {$btc['change_percentage_24h']}%");
                }
            }
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }

        // Test 3: Test Market Statistics
        $this->newLine();
        $this->info('3. Testing Market Statistics:');
        try {
            $stats = $marketService->getMarketStatistics();
            $this->info("   ✓ Market Statistics:");
            $this->line("     - Total Market Cap: \${$stats['total_market_cap']}");
            $this->line("     - Total 24h Volume: \${$stats['total_volume_24h']}");
            $this->line("     - BTC Dominance: {$stats['btc_dominance']}%");
            $this->line("     - Active Cryptocurrencies: {$stats['active_cryptocurrencies']}");
        } catch (\Exception $e) {
            $this->error("   ✗ Error: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== Markets Module Test Complete ===');
        
        return 0;
    }
}