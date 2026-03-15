<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Services\EducationalDepositService;
use App\Services\WalletAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Class DepositController
 * 
 * Handles deposit-related operations for the crypto exchange simulation.
 * This controller manages both educational and simulated deposit functionality.
 * 
 * @package App\Http\Controllers\Api
 */
class DepositController extends Controller
{
    protected EducationalDepositService $depositService;
    protected WalletAddressService $walletService;

    public function __construct(EducationalDepositService $depositService, WalletAddressService $walletService)
    {
        $this->depositService = $depositService;
        $this->walletService = $walletService;
    }

    /**
     * Get user's deposits with pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Get query parameters for filtering and pagination
            $status = $request->query('status');
            $currency = $request->query('currency');
            $limit = min($request->query('limit', 50), 100); // Max 100 records
            $offset = $request->query('offset', 0);
            
            // Build query with more detailed information for transaction pool
            $query = Deposit::select([
                'id', 'currency', 'network', 'amount', 'fee', 'net_amount', 
                'status', 'txid', 'confirmations', 'required_confirmations',
                'wallet_address', 'created_at', 'updated_at', 'completed_at'
            ])
            ->where('user_id', $user->id);
            
            // Apply filters
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }
            
            if ($currency) {
                $query->where('currency', strtoupper($currency));
            }
            
            // Get total count for pagination
            $total = $query->count();
            
            // Apply pagination and ordering
            $deposits = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            // Add computed fields for transaction pool
            $deposits = $deposits->map(function ($deposit) {
                $deposit->confirmation_progress = $deposit->required_confirmations > 0 
                    ? min(100, ($deposit->confirmations / $deposit->required_confirmations) * 100)
                    : 100;
                    
                $deposit->estimated_completion = $this->getEstimatedCompletion($deposit);
                $deposit->blockchain_explorer_url = $this->getBlockchainExplorerUrl($deposit);
                
                return $deposit;
            });

            return response()->json([
                'success' => true,
                'data' => $deposits,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total
                ],
                'filters' => [
                    'status' => $status,
                    'currency' => $currency
                ],
                'educational_note' => 'In real scam exchanges, deposit history may be fabricated or manipulated'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get deposits', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deposits'
            ], 500);
        }
    }

    /**
     * Get estimated completion time for a deposit
     */
    private function getEstimatedCompletion($deposit): ?string
    {
        if ($deposit->status === 'completed') {
            return null;
        }
        
        if ($deposit->status === 'failed') {
            return null;
        }
        
        $estimatedTimes = [
            'BTC' => 60, // 60 minutes
            'ETH' => 15, // 15 minutes
            'USDT' => 15,
            'USDC' => 15,
            'BNB' => 10
        ];
        
        $baseTime = $estimatedTimes[$deposit->currency] ?? 30;
        
        if ($deposit->status === 'confirming' && $deposit->required_confirmations > 0) {
            $remainingConfirmations = max(0, $deposit->required_confirmations - $deposit->confirmations);
            $estimatedMinutes = $remainingConfirmations * ($baseTime / $deposit->required_confirmations);
            
            if ($estimatedMinutes < 1) {
                return 'Less than 1 minute';
            } elseif ($estimatedMinutes < 60) {
                return round($estimatedMinutes) . ' minutes';
            } else {
                return round($estimatedMinutes / 60, 1) . ' hours';
            }
        }
        
        return $baseTime . ' minutes';
    }

    /**
     * Get blockchain explorer URL for a transaction
     */
    private function getBlockchainExplorerUrl($deposit): ?string
    {
        if (!$deposit->txid) {
            return null;
        }
        
        $explorers = [
            'BTC' => 'https://blockchair.com/bitcoin/transaction/',
            'ETH' => 'https://etherscan.io/tx/',
            'USDT' => 'https://etherscan.io/tx/',
            'USDC' => 'https://etherscan.io/tx/',
            'BNB' => 'https://bscscan.com/tx/'
        ];
        
        $baseUrl = $explorers[$deposit->currency] ?? null;
        
        return $baseUrl ? $baseUrl . $deposit->txid : null;
    }

    /**
     * Get specific deposit details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = $request->user();
            $deposit = Deposit::where('user_id', $user->id)->findOrFail($id);

            return response()->json([
                'success' => true,
                'deposit' => $deposit,
                'educational_warning' => 'In real scams, these details may be completely fabricated'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit not found'
            ], 404);
        }
    }

    /**
     * Generate deposit address for crypto
     */
    public function generateAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:BTC,ETH,USDT,LTC,ADA,DOT,XRP,BNB,SOL,USDC,MATIC',
            'network' => 'required|string',
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
            $network = $request->input('network');

            // Get or create deposit address using WalletAddressService
            $depositAddress = $this->walletService->getDepositAddress($user, $currency, $network);

            if (!$depositAddress) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate deposit address'
                ], 400);
            }

            // Generate QR code URL
            $qrData = "{$currency}:{$depositAddress->address}";
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrData);

            return response()->json([
                'success' => true,
                'data' => [
                    'address' => $depositAddress->address,
                    'currency' => $depositAddress->currency,
                    'network' => $depositAddress->network,
                    'qr_code' => $qrCodeUrl,
                    'formatted_address' => $depositAddress->formatted_address,
                    'instructions' => $this->getDepositInstructions($currency, $network)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate deposit address', [
                'user_id' => $request->user()->id,
                'currency' => $request->input('currency'),
                'network' => $request->input('network'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate deposit address'
            ], 500);
        }
    }

    /**
     * Store MetaMask wallet address for user
     */
    public function storeMetaMaskAddress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:ETH,USDT,USDC,BNB',
            'network' => 'required|string|in:Ethereum,BSC,Polygon',
            'address' => 'required|string|regex:/^0x[a-fA-F0-9]{40}$/',
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
            $network = $request->input('network');
            $address = $request->input('address');

            // Store the MetaMask address
            $depositAddress = $this->walletService->storeMetaMaskAddress($user, $currency, $network, $address);

            Log::info("MetaMask address stored for user {$user->id}: {$currency}/{$network} - {$address}");

            return response()->json([
                'success' => true,
                'message' => 'MetaMask address stored successfully',
                'data' => [
                    'address' => $depositAddress->address,
                    'currency' => $depositAddress->currency,
                    'network' => $depositAddress->network,
                    'formatted_address' => $depositAddress->formatted_address
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store MetaMask address', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store MetaMask address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove MetaMask wallet address for user
     */
    public function removeMetaMaskAddress(Request $request): JsonResponse
    {
        // Log the incoming request for debugging
        Log::info('MetaMask disconnect request received', [
            'method' => $request->method(),
            'all_data' => $request->all(),
            'query_data' => $request->query->all(),
            'json_data' => $request->json() ? $request->json()->all() : null,
            'content' => $request->getContent(),
            'content_type' => $request->header('Content-Type')
        ]);

        // Handle both JSON body and query parameters
        $inputData = array_merge($request->query->all(), $request->all());
        
        $validator = Validator::make($inputData, [
            'currency' => 'required|string|max:10',
            'network' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            Log::warning('MetaMask disconnect validation failed', [
                'errors' => $validator->errors(),
                'input_data' => $inputData,
                'request_all' => $request->all(),
                'query_all' => $request->query->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'debug_info' => [
                    'received_data' => $inputData,
                    'content_type' => $request->header('Content-Type'),
                    'method' => $request->method()
                ]
            ], 422);
        }

        try {
            $user = $request->user();
            $currency = strtoupper($inputData['currency']);
            $network = $inputData['network'];

            // Find and deactivate the MetaMask address
            $depositAddress = $user->depositAddresses()
                ->where('currency', $currency)
                ->where('network', $network)
                ->where('is_active', true)
                ->first();

            if ($depositAddress) {
                $depositAddress->update(['is_active' => false]);
                
                Log::info("MetaMask address removed for user {$user->id}: {$currency}/{$network}");

                return response()->json([
                    'success' => true,
                    'message' => 'MetaMask address removed successfully'
                ]);
            } else {
                Log::info("No active MetaMask address found for user {$user->id}: {$currency}/{$network}");
                
                return response()->json([
                    'success' => false,
                    'message' => 'No active MetaMask address found for this currency and network'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Failed to remove MetaMask address', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove MetaMask address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's deposit addresses
     */
    public function getUserAddresses(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $addresses = $user->depositAddresses()
                ->where('is_active', true)
                ->orderBy('currency')
                ->orderBy('network')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $addresses->map(function ($address) {
                    return [
                        'id' => $address->id,
                        'currency' => $address->currency,
                        'network' => $address->network,
                        'address' => $address->address,
                        'formatted_address' => $address->formatted_address,
                        'last_used_at' => $address->last_used_at,
                        'created_at' => $address->created_at
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user addresses', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve addresses'
            ], 500);
        }
    }

    /**
     * Get supported currencies and networks
     */
    public function getSupportedCurrencies(): JsonResponse
    {
        try {
            $currencies = $this->walletService->getSupportedCurrencies();
            
            return response()->json([
                'success' => true,
                'data' => $currencies
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get supported currencies', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve supported currencies'
            ], 500);
        }
    }

    /**
     * Get deposit instructions for currency/network
     */
    private function getDepositInstructions(string $currency, string $network): array
    {
        $instructions = [
            'BTC' => [
                'Bitcoin' => [
                    'Send only Bitcoin (BTC) to this address',
                    'Minimum deposit: 0.001 BTC',
                    'Network confirmations required: 6',
                    'Estimated arrival time: 10-60 minutes'
                ]
            ],
            'ETH' => [
                'Ethereum' => [
                    'Send only Ethereum (ETH) to this address',
                    'Use Ethereum network only',
                    'Minimum deposit: 0.01 ETH',
                    'Network confirmations required: 12',
                    'Estimated arrival time: 5-15 minutes'
                ],
                'BSC' => [
                    'Send only Ethereum (ETH) to this address',
                    'Use Binance Smart Chain (BSC) network',
                    'Lower fees than Ethereum network',
                    'Network confirmations required: 15',
                    'Estimated arrival time: 3-10 minutes'
                ]
            ],
            'USDT' => [
                'Ethereum' => [
                    'Send only USDT (ERC-20) to this address',
                    'Use Ethereum network only',
                    'Higher fees but most secure',
                    'Minimum deposit: 10 USDT'
                ],
                'BSC' => [
                    'Send only USDT (BEP-20) to this address',
                    'Use Binance Smart Chain network',
                    'Lower fees than Ethereum',
                    'Minimum deposit: 10 USDT'
                ],
                'Tron' => [
                    'Send only USDT (TRC-20) to this address',
                    'Use Tron network only',
                    'Lowest fees available',
                    'Minimum deposit: 10 USDT'
                ]
            ]
        ];

        return $instructions[$currency][$network] ?? [
            "Send only {$currency} to this address",
            "Use {$network} network only",
            'Double-check the network before sending',
            'Contact support if funds don\'t arrive'
        ];
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
            Log::error('Failed to create fiat deposit', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

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
            'currency' => 'required|string|in:BTC,ETH,USDT,LTC,ADA,DOT,XRP,BNB,SOL,USDC,MATIC',
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
                    'deposit' => $result['deposit']->fresh(),
                    'message' => 'Crypto deposit simulated successfully',
                    'educational_note' => $result['educational_note']
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to simulate crypto deposit', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate deposit'
            ], 500);
        }
    }

    /**
     * Process incoming deposit (webhook simulation)
     */
    public function processIncomingDeposit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'txid' => 'required|string',
            'confirmations' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $txid = $request->input('txid');
            $confirmations = $request->input('confirmations');

            $result = $this->depositService->processDepositConfirmation($txid, $confirmations);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'deposit' => $result['deposit']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to process incoming deposit', [
                'txid' => $request->input('txid'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process deposit'
            ], 500);
        }
    }

    /**
     * Submit deposit with proof (image upload)
     */
    public function submitWithProof(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:BTC,ETH,USDT,LTC,ADA,DOT,XRP,BNB,SOL,USDC,MATIC',
            'amount' => 'required|numeric|min:0.001',
            'wallet_address' => 'required|string',
            'network' => 'required|string',
            'transaction_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
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
            
            // Handle image upload
            $imagePath = null;
            if ($request->hasFile('transaction_image')) {
                $image = $request->file('transaction_image');
                $imageName = time() . '_' . $user->id . '_' . $image->getClientOriginalName();
                $imagePath = $image->storeAs('deposits/proofs', $imageName, 'public');
            }

            $data = [
                'user_id' => $user->id,
                'currency' => $request->input('currency'),
                'type' => 'crypto',
                'amount' => $request->input('amount'),
                'wallet_address' => $request->input('wallet_address'),
                'network' => $request->input('network'),
                'transaction_image' => $imagePath,
                'status' => 'pending', // Requires manual verification
            ];

            $result = $this->depositService->createDepositWithProof($data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'deposit' => $result['deposit'],
                    'message' => 'Deposit submitted successfully. We will verify your transaction and credit your account.',
                    'educational_note' => $result['educational_note']
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to submit deposit with proof', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit deposit'
            ], 500);
        }
    }

    /**
     * Get deposit statistics
     */
    public function getDepositStatistics(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $stats = [
                'total_deposits' => Deposit::where('user_id', $user->id)->count(),
                'total_amount' => Deposit::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->sum('amount'),
                'pending_deposits' => Deposit::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count(),
                'completed_deposits' => Deposit::where('user_id', $user->id)
                    ->where('status', 'completed')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'educational_note' => 'These statistics help track your deposit activity'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get deposit statistics', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
}