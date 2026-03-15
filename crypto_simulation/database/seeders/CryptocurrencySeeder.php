<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cryptocurrency;

class CryptocurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cryptocurrencies = [
            [
                'symbol' => 'USD',
                'name' => 'US Dollar',
                'current_price' => '1.00000000',
                'market_cap' => '0.00',
                'volume_24h' => '0.00',
                'price_change_24h' => '0.00000000',
                'price_change_percentage_24h' => '0.0000',
                'circulating_supply' => null,
                'logo_url' => null,
                'volatility' => 0.001,
                'is_active' => true,
            ],
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'current_price' => '67000.00000000',
                'market_cap' => '1270000000000.00',
                'volume_24h' => '28000000000.00',
                'price_change_24h' => '1500.00000000',
                'price_change_percentage_24h' => '2.3000',
                'circulating_supply' => '19000000.00',
                'logo_url' => null,
                'volatility' => 0.05,
                'is_active' => true,
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'current_price' => '3400.00000000',
                'market_cap' => '408000000000.00',
                'volume_24h' => '12000000000.00',
                'price_change_24h' => '60.00000000',
                'price_change_percentage_24h' => '1.8000',
                'circulating_supply' => '120000000.00',
                'logo_url' => null,
                'volatility' => 0.06,
                'is_active' => true,
            ],
            [
                'symbol' => 'SOL',
                'name' => 'Solana',
                'current_price' => '145.00000000',
                'market_cap' => '68000000000.00',
                'volume_24h' => '2000000000.00',
                'price_change_24h' => '5.80000000',
                'price_change_percentage_24h' => '4.2000',
                'circulating_supply' => '469000000.00',
                'logo_url' => null,
                'volatility' => 0.08,
                'is_active' => true,
            ],
            [
                'symbol' => 'USDT',
                'name' => 'Tether',
                'current_price' => '1.00000000',
                'market_cap' => '95000000000.00',
                'volume_24h' => '45000000000.00',
                'price_change_24h' => '0.00100000',
                'price_change_percentage_24h' => '0.1000',
                'circulating_supply' => '95000000000.00',
                'logo_url' => null,
                'volatility' => 0.001,
                'is_active' => true,
            ],
            [
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'current_price' => '1.00000000',
                'market_cap' => '32000000000.00',
                'volume_24h' => '8000000000.00',
                'price_change_24h' => '-0.00050000',
                'price_change_percentage_24h' => '-0.0500',
                'circulating_supply' => '32000000000.00',
                'logo_url' => null,
                'volatility' => 0.001,
                'is_active' => true,
            ],
        ];

        foreach ($cryptocurrencies as $crypto) {
            Cryptocurrency::updateOrCreate(
                ['symbol' => $crypto['symbol']],
                $crypto
            );
        }
    }
}
