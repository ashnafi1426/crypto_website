<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    private WithdrawalService $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    /**
     * Get user withdrawals
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

            $result = $this->withdrawalService->getUserWithdrawals($user, array_filter($filters));

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['withdrawals'],
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
                'message' => 'Failed to retrieve withdrawals'
            ], 500);
        }
    }

    /**
     * Create withdrawal request
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:BTC,ETH,LTC,BCH,XRP,USD,EUR,GBP',
            'amount' => 'required|numeric|min:0.001',
            'type' => 'required|string|in:crypto,fiat',
            'to_address' => 'required_if:type,crypto|string',
            'payment_method' => 'required_if:type,fiat|string|in:bank_transfer,paypal',
            'payment_details' => 'required_if:type,fiat|array',
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
                'currency' => $request->input('currency'),
                'amount' => $request->input('amount'),
                'type' => $request->input('type'),
                'to_address' => $request->input('to_address'),
                'payment_method' => $request->input('payment_method'),
                'payment_details' => $request->input('payment_details'),
                'metadata' => $request->input('metadata', []),
            ];

            $result = $this->withdrawalService->createWithdrawal($user, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['withdrawal'],
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
                'message' => 'Failed to create withdrawal request'
            ], 500);
        }
    }

    /**
     * Verify withdrawal with email code
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'verification_code' => 'required|string|size:6',
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
            $code = $request->input('verification_code');

            $result = $this->withdrawalService->verifyWithdrawal($user, $id, $code);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['withdrawal'] ?? null,
                    'requires_2fa' => $result['requires_2fa'] ?? false,
                    'message' => $result['message']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify withdrawal'
            ], 500);
        }
    }

    /**
     * Verify 2FA for withdrawal
     */
    public function verify2FA(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'two_factor_code' => 'required|string|size:6',
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
            $code = $request->input('two_factor_code');

            $result = $this->withdrawalService->verify2FA($user, $id, $code);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['withdrawal'],
                    'message' => $result['message']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify 2FA'
            ], 500);
        }
    }

    /**
     * Cancel withdrawal (only if pending)
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $withdrawal = \App\Models\Withdrawal::where('id', $id)
                                               ->where('user_id', $user->id)
                                               ->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ], 404);
            }

            if ($withdrawal->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal cannot be cancelled in current state'
                ], 400);
            }

            $withdrawal->update(['status' => 'cancelled']);

            // Release held funds
            app(\App\Services\Contracts\WalletManagerInterface::class)->releaseFunds(
                $user,
                $withdrawal->currency,
                $withdrawal->amount,
                "Withdrawal #{$withdrawal->id} cancelled"
            );

            return response()->json([
                'success' => true,
                'data' => $withdrawal,
                'message' => 'Withdrawal cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel withdrawal'
            ], 500);
        }
    }

    /**
     * Get withdrawal by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            
            $withdrawal = \App\Models\Withdrawal::where('id', $id)
                                               ->where('user_id', $user->id)
                                               ->with(['approvedBy', 'processedBy'])
                                               ->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $withdrawal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve withdrawal'
            ], 500);
        }
    }
}