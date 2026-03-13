<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WalletManager;
use App\Models\TransactionRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    private WalletManager $walletManager;

    public function __construct(WalletManager $walletManager)
    {
        $this->walletManager = $walletManager;
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all wallets for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->walletManager->getUserWallets($user->id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            // Get portfolio value
            $portfolioResult = $this->walletManager->getPortfolioValue($user->id);

            return response()->json([
                'success' => true,
                'wallets' => $result['wallets'],
                'portfolio' => [
                    'total_value' => $portfolioResult['total_value'] ?? '0.00000000',
                    'currency' => 'USD',
                    'breakdown' => $portfolioResult['portfolio_breakdown'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve wallets'
            ], 500);
        }
    }

    /**
     * Get specific wallet for the authenticated user.
     */
    public function show(Request $request, string $cryptocurrency): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->walletManager->getBalance($user->id, strtoupper($cryptocurrency));

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 404);
            }

            return response()->json([
                'success' => true,
                'wallet' => [
                    'cryptocurrency' => $result['cryptocurrency'],
                    'balance' => $result['balance'],
                    'reserved_balance' => $result['reserved_balance'],
                    'available_balance' => $result['available_balance']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve wallet information'
            ], 500);
        }
    }

    /**
     * Get transaction history for a specific wallet.
     */
    public function transactions(Request $request, string $cryptocurrency): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'type' => 'string|in:credit,debit,reserve,release,all'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $perPage = $request->get('per_page', 20);
            $type = $request->get('type', 'all');

            $query = TransactionRecord::where('user_id', $user->id)
                ->where('cryptocurrency_symbol', strtoupper($cryptocurrency))
                ->orderBy('created_at', 'desc');

            if ($type !== 'all') {
                $query->where('transaction_type', $type);
            }

            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'transactions' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                    'has_more' => $transactions->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transaction history'
            ], 500);
        }
    }

    /**
     * Get portfolio summary for the authenticated user.
     */
    public function portfolio(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->walletManager->getPortfolioValue($user->id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'portfolio' => [
                    'total_value' => $result['total_value'],
                    'currency' => $result['currency'],
                    'breakdown' => $result['portfolio_breakdown'],
                    'last_updated' => $result['last_updated']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate portfolio value'
            ], 500);
        }
    }
}