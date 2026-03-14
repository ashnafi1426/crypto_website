<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller
{
    private DepositService $depositService;

    public function __construct(DepositService $depositService)
    {
        $this->depositService = $depositService;
    }

    /**
     * Get user deposits
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $filters = [
                'currency' => $request->input('currency'),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'per_page' => $request->input('per_page', 20),
            ];

            $result = $this->depositService->getUserDeposits($user, array_filter($filters));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['deposits'],
                    'summary' => $result['summary']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deposits'
            ], 500);
        }
    }

    /**
     * Generate deposit address for crypto
     */
    public function generateAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:BTC,ETH,LTC,BCH,XRP',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            $currency = $request->input('currency');

            $result = $this->depositService->generateDepositAddress($user, $currency);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'address' => $result['address'],
                        'currency' => $result['currency'],
                        'qr_code' => $result['qr_code'],
                        'instructions' => $result['instructions']
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate deposit address'
            ], 500);
        }
    }

    /**
     * Create fiat deposit
     */
    public function createFiatDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:USD,EUR,GBP',
            'amount' => 'required|numeric|min:10',
            'payment_method' => 'required|string|in:bank_transfer,credit_card,paypal',
            'payment_details' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            $data = [
                'user_id' => $user->id,
                'currency' => $request->input('currency'),
                'type' => 'fiat',
                'amount' => $request->input('amount'),
                'payment_method' => $request->input('payment_method'),
                'payment_details' => $request->input('payment_details'),
                'payment_reference' => $request->input('payment_reference'),
            ];

            $result = $this->depositService->processFiatDeposit($data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['deposit'],
                    'message' => $result['message']
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create fiat deposit'
            ], 500);
        }
    }

    /**
     * Simulate crypto deposit (for testing)
     */
    public function simulateCryptoDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:BTC,ETH,LTC,BCH,XRP',
            'amount' => 'required|numeric|min:0.001',
            'wallet_address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            
            // Generate mock transaction ID
            $txid = bin2hex(random_bytes(32));
            
            $data = [
                'user_id' => $user->id,
                'currency' => $request->input('currency'),
                'type' => 'crypto',
                'amount' => $request->input('amount'),
                'wallet_address' => $request->input('wallet_address'),
                'txid' => $txid,
            ];

            $result = $this->depositService->createDeposit($data);

            if ($result['success']) {
                // Simulate confirmations
                $this->depositService->processDepositConfirmation($txid, 3);

                return response()->json([
                    'success' => true,
                    'data' => $result['deposit']->fresh(),
                    'message' => 'Crypto deposit simulated successfully'
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate crypto deposit'
            ], 500);
        }
    }

    /**
     * Get deposit by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $deposit = \App\Models\Deposit::where('id', $id)
                                         ->where('user_id', $user->id)
                                         ->with(['processedBy'])
                                         ->first();

            if (!$deposit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deposit not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $deposit
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deposit'
            ], 500);
        }
    }
}