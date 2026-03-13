<?php

require_once 'vendor/autoload.php';

use App\Services\PriceSimulator;
use App\Models\Cryptocurrency;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing PriceSimulator...\n";

$simulator = new PriceSimulator();

// Test getting current price
echo "\n1. Testing getCurrentPrice for BTC:\n";
$btcPrice = $simulator->getCurrentPrice('BTC');
print_r($btcPrice);

// Test updating prices
echo "\n2. Testing updatePrices:\n";
$result = $simulator->updatePrices();
print_r($result);

// Test getting price history
echo "\n3. Testing getPriceHistory for BTC:\n";
$history = $simulator->getPriceHistory('BTC', ['start' => '2024-01-01', 'end' => '2024-12-31']);
echo "History count: " . $history['count'] . "\n";

// Test setting volatility
echo "\n4. Testing setCryptocurrencyVolatility for BTC:\n";
$volatilityResult = $simulator->setCryptocurrencyVolatility('BTC', 0.08);
echo "Volatility set: " . ($volatilityResult ? 'true' : 'false') . "\n";

// Test candlestick data
echo "\n5. Testing generateCandlestickData for BTC:\n";
$candlesticks = $simulator->generateCandlestickData('BTC', '1h');
echo "Candlesticks count: " . count($candlesticks['candlesticks']) . "\n";

echo "\nAll tests completed!\n";