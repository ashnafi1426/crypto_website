<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Cryptocurrency;
use App\Models\Market;
use App\Models\Trade;
use App\Services\MarketService;

// Initialize database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => __DIR__ . '/database/database.sqlite',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "=== Testing Markets Module Implementation ===\n\n";

// Test 1: Check cryptocurrency data
echo "1. Testing Cryptocurrency Data:\n";
$cryptos = Cryptocurrency::active()->get();
foreach ($cryptos as $crypto) {
    echo "   - {$crypto->symbol}: {$crypto->name}\n";
    echo "     Price: \${$crypto->current_price}\n";
    echo "     Market Cap: \${$crypto->market_cap}\n";
    echo "     24h Volume: \${$crypto->volume_24h}\n";
    echo "     24h Change: {$crypto->price_change_percentage_24h}%\n\n";
}

// Test 2: Test MarketService
echo "2. Testing MarketService:\n";
$marketService = new MarketService();

try {
    $markets = $marketService->getAllMarkets();
    echo "   ✓ Successfully retrieved " . count($markets) . " markets\n";
    
    if (!empty($markets)) {
        $btc = array_filter($markets, fn($m) => $m['symbol'] === 'BTC');
        if (!empty($btc)) {
            $btc = array_values($btc)[0];
            echo "   ✓ BTC Market Data:\n";
            echo "     - Pair: {$btc['pair']}\n";
            echo "     - Price: \${$btc['price']}\n";
            echo "     - Market Cap: \${$btc['market_cap']}\n";
            echo "     - 24h Volume: \${$btc['volume_24h']}\n";
            echo "     - 24h Change: {$btc['change_percentage_24h']}%\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Test Market Statistics
echo "\n3. Testing Market Statistics:\n";
try {
    $stats = $marketService->getMarketStatistics();
    echo "   ✓ Market Statistics:\n";
    echo "     - Total Market Cap: \${$stats['total_market_cap']}\n";
    echo "     - Total 24h Volume: \${$stats['total_volume_24h']}\n";
    echo "     - BTC Dominance: {$stats['btc_dominance']}%\n";
    echo "     - Active Cryptocurrencies: {$stats['active_cryptocurrencies']}\n";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Test API endpoints
echo "\n4. Testing API Endpoints:\n";

// Test cryptocurrencies endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/cryptocurrencies');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "   ✓ /api/cryptocurrencies endpoint working\n";
        echo "     - Returned " . count($data['cryptocurrencies']) . " cryptocurrencies\n";
    } else {
        echo "   ✗ API returned error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "   ✗ API endpoint failed with HTTP $httpCode\n";
}

// Test statistics endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/cryptocurrencies/statistics');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "   ✓ /api/cryptocurrencies/statistics endpoint working\n";
        echo "     - Statistics data available\n";
    } else {
        echo "   ✗ Statistics API returned error: " . ($data['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "   ✗ Statistics API endpoint failed with HTTP $httpCode\n";
}

// Test WebSocket endpoint
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:8000/api/ws/market-data');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['type'] === 'market_data') {
        echo "   ✓ /api/ws/market-data endpoint working\n";
        echo "     - Real-time market data available\n";
    } else {
        echo "   ✗ WebSocket API returned unexpected data\n";
    }
} else {
    echo "   ✗ WebSocket API endpoint failed with HTTP $httpCode\n";
}

echo "\n=== Markets Module Test Complete ===\n";