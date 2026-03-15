<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Validator;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing Remove MetaMask Address Validation\n";
echo "============================================\n\n";

// Test the validation rules
$testData = [
    'currency' => 'ETH',
    'network' => 'Ethereum'
];

echo "Test data:\n";
print_r($testData);

$validator = Validator::make($testData, [
    'currency' => 'required|string|max:10',
    'network' => 'required|string|max:50'
]);

if ($validator->fails()) {
    echo "❌ Validation failed:\n";
    print_r($validator->errors()->toArray());
} else {
    echo "✅ Validation passed!\n";
}

// Test with empty data
echo "\nTesting with empty data:\n";
$emptyData = [];
$validator2 = Validator::make($emptyData, [
    'currency' => 'required|string|max:10',
    'network' => 'required|string|max:50'
]);

if ($validator2->fails()) {
    echo "❌ Validation failed (expected):\n";
    print_r($validator2->errors()->toArray());
} else {
    echo "✅ Validation passed!\n";
}