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
                'volatility' => 0.001,
                'is_active' => true,
            ],
            [
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'current_price' => '45000.00000000',
                'volatility' => 0.05,
                'is_active' => true,
            ],
            [
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'current_price' => '3000.00000000',
                'volatility' => 0.06,
                'is_active' => true,
            ],
            [
                'symbol' => 'LTC',
                'name' => 'Litecoin',
                'current_price' => '150.00000000',
                'volatility' => 0.07,
                'is_active' => true,
            ],
            [
                'symbol' => 'ADA',
                'name' => 'Cardano',
                'current_price' => '0.50000000',
                'volatility' => 0.08,
                'is_active' => true,
            ],
            [
                'symbol' => 'DOT',
                'name' => 'Polkadot',
                'current_price' => '25.00000000',
                'volatility' => 0.09,
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
