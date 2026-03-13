<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your crypto exchange
| backend application. These routes are loaded by the RouteServiceProvider
| and all of them will be assigned to the "api" middleware group.
|
*/

// Public routes (no authentication required)
Route::prefix('auth')->group(function () {
    Route::post('/register', [App\Http\Controllers\Api\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/password/reset', [App\Http\Controllers\Api\AuthController::class, 'requestPasswordReset']);
    Route::post('/password/reset/confirm', [App\Http\Controllers\Api\AuthController::class, 'confirmPasswordReset']);
});

// Public market data routes
Route::prefix('cryptocurrencies')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MarketDataController::class, 'index']);
    Route::get('/{symbol}/price-history', [App\Http\Controllers\Api\MarketDataController::class, 'priceHistory']);
    Route::get('/{symbol}/candlestick', [App\Http\Controllers\Api\MarketDataController::class, 'candlestick']);
});

Route::get('/orderbook/{cryptocurrency}', [App\Http\Controllers\Api\TradingController::class, 'orderBook']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/auth/logout', [App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/auth/user', [App\Http\Controllers\Api\AuthController::class, 'user']);

    // Debug route for wallet testing
    Route::get('/debug/wallets', function (Request $request) {
        $user = $request->user();
        $wallets = \App\Models\Wallet::where('user_id', $user->id)->get();
        return response()->json([
            'user_id' => $user->id,
            'wallet_count' => $wallets->count(),
            'wallets' => $wallets->toArray()
        ]);
    });

    // Wallet management routes
    Route::prefix('wallets')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\WalletController::class, 'index']);
        Route::get('/portfolio', [App\Http\Controllers\Api\WalletController::class, 'portfolio']);
        Route::get('/{cryptocurrency}', [App\Http\Controllers\Api\WalletController::class, 'show']);
        Route::get('/{cryptocurrency}/transactions', [App\Http\Controllers\Api\WalletController::class, 'transactions']);
    });

    // Trading routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\TradingController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Api\TradingController::class, 'store']);
        Route::delete('/{orderId}', [App\Http\Controllers\Api\TradingController::class, 'cancel']);
    });

    // Admin routes (admin users only)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Dashboard and analytics
        Route::get('/dashboard', [App\Http\Controllers\Api\AdminController::class, 'dashboard']);
        Route::get('/analytics', [App\Http\Controllers\Api\AdminController::class, 'getAnalytics']);
        Route::get('/real-time-metrics', [App\Http\Controllers\Api\AdminController::class, 'getRealTimeMetrics']);
        Route::get('/system-metrics', [App\Http\Controllers\Api\AdminController::class, 'systemMetrics']);
        
        // User management
        Route::get('/users', [App\Http\Controllers\Api\AdminController::class, 'users']);
        Route::get('/users/{userId}', [App\Http\Controllers\Api\AdminController::class, 'userDetails']);
        Route::post('/users/{userId}/adjust-balance', [App\Http\Controllers\Api\AdminController::class, 'adjustBalance']);
        Route::post('/users/{userId}/toggle-status', [App\Http\Controllers\Api\AdminController::class, 'toggleUserStatus']);
        
        // KYC Management
        Route::prefix('kyc')->group(function () {
            Route::get('/submissions', [App\Http\Controllers\Api\AdminController::class, 'getKycSubmissions']);
            Route::get('/statistics', [App\Http\Controllers\Api\AdminController::class, 'getKycStatistics']);
            Route::post('/{documentId}/approve', [App\Http\Controllers\Api\AdminController::class, 'approveKyc']);
            Route::post('/{documentId}/reject', [App\Http\Controllers\Api\AdminController::class, 'rejectKyc']);
        });

        // Support Tickets Management
        Route::prefix('support')->group(function () {
            Route::get('/tickets', [App\Http\Controllers\Api\AdminController::class, 'getSupportTickets']);
            Route::get('/statistics', [App\Http\Controllers\Api\AdminController::class, 'getSupportStatistics']);
            Route::post('/tickets/{ticketId}/assign', [App\Http\Controllers\Api\AdminController::class, 'assignTicket']);
            Route::post('/tickets/{ticketId}/resolve', [App\Http\Controllers\Api\AdminController::class, 'resolveTicket']);
        });

        // Referral Program Management
        Route::prefix('referrals')->group(function () {
            Route::get('/programs', [App\Http\Controllers\Api\AdminController::class, 'getReferralPrograms']);
            Route::get('/statistics', [App\Http\Controllers\Api\AdminController::class, 'getReferralStatistics']);
            Route::post('/programs/{programId}/commission-rate', [App\Http\Controllers\Api\AdminController::class, 'updateCommissionRate']);
        });

        // Investment Management
        Route::prefix('investments')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\AdminController::class, 'getInvestments']);
            Route::get('/statistics', [App\Http\Controllers\Api\AdminController::class, 'getInvestmentStatistics']);
            Route::post('/{investmentId}/cancel', [App\Http\Controllers\Api\AdminController::class, 'cancelInvestment']);
        });

        // Wallet Management
        Route::prefix('wallets')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\AdminController::class, 'getWallets']);
            Route::get('/statistics', [App\Http\Controllers\Api\AdminController::class, 'getWalletStatistics']);
        });

        // Deposits & Withdrawals Management
        Route::prefix('transactions')->group(function () {
            Route::get('/deposits', [App\Http\Controllers\Api\AdminController::class, 'getDeposits']);
            Route::get('/withdrawals', [App\Http\Controllers\Api\AdminController::class, 'getWithdrawals']);
            Route::post('/withdrawals/{transactionId}/approve', [App\Http\Controllers\Api\AdminController::class, 'approveWithdrawal']);
            Route::post('/withdrawals/{transactionId}/reject', [App\Http\Controllers\Api\AdminController::class, 'rejectWithdrawal']);
        });
        
        // Security and monitoring
        Route::get('/suspicious-activities', [App\Http\Controllers\Api\AdminController::class, 'suspiciousActivities']);
        
        // System controls
        Route::post('/cryptocurrencies/{symbol}/override-price', [App\Http\Controllers\Api\AdminController::class, 'overridePrice']);
        Route::post('/maintenance-mode', [App\Http\Controllers\Api\AdminController::class, 'maintenanceMode']);
    });
});