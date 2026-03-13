<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Trade;
use App\Models\Wallet;
use App\Models\Cryptocurrency;
use App\Models\TransactionRecord;
use App\Services\AdminPanelService;
use App\Services\WalletManager;
use App\Services\KycManagementService;
use App\Services\SupportTicketService;
use App\Services\ReferralManagementService;
use App\Services\InvestmentManagementService;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    private AdminPanelService $adminService;
    private WalletManager $walletManager;
    private KycManagementService $kycService;
    private SupportTicketService $ticketService;
    private ReferralManagementService $referralService;
    private InvestmentManagementService $investmentService;
    private AnalyticsService $analyticsService;

    public function __construct(
        AdminPanelService $adminService,
        WalletManager $walletManager,
        KycManagementService $kycService,
        SupportTicketService $ticketService,
        ReferralManagementService $referralService,
        InvestmentManagementService $investmentService,
        AnalyticsService $analyticsService
    ) {
        $this->adminService = $adminService;
        $this->walletManager = $walletManager;
        $this->kycService = $kycService;
        $this->ticketService = $ticketService;
        $this->referralService = $referralService;
        $this->investmentService = $investmentService;
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive system metrics and dashboard data
     */
    public function dashboard(): JsonResponse
    {
        try {
            $metrics = [
                'stats' => [
                    'total_users' => User::count(),
                    'new_users_week' => User::where('created_at', '>=', now()->subWeek())->count(),
                    'total_deposits' => TransactionRecord::where('type', 'deposit')->sum('amount'),
                    'total_withdrawals' => TransactionRecord::where('type', 'withdrawal')->sum('amount'),
                    'active_investments' => Order::where('status', 'filled')->sum('quantity'),
                    'open_tickets' => 47, // Mock data - implement ticket system later
                    'kyc_pending' => User::where('kyc_status', 'pending')->count(),
                    'platform_revenue' => TransactionRecord::where('type', 'fee')->sum('amount'),
                    'daily_trades' => Trade::whereDate('created_at', today())->count(),
                ],
                'growth' => $this->getUserGrowthData(),
                'revenue' => $this->getRevenueData(),
                'recent_transactions' => $this->getRecentTransactions(),
                'asset_distribution' => $this->getAssetDistribution(),
                'system_status' => [
                    'status' => 'operational',
                    'users_online' => rand(1200, 1300),
                    'today_revenue' => TransactionRecord::where('type', 'fee')
                        ->whereDate('created_at', today())
                        ->sum('amount'),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system metrics
     */
    public function systemMetrics(): JsonResponse
    {
        try {
            $metrics = [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('status', 'active')->count(),
                    'suspended' => User::where('status', 'suspended')->count(),
                    'new_today' => User::whereDate('created_at', today())->count(),
                ],
                'trading' => [
                    'total_volume' => Trade::sum('quantity'),
                    'daily_volume' => Trade::whereDate('created_at', today())->sum('quantity'),
                    'active_orders' => Order::where('status', 'pending')->count(),
                    'completed_trades' => Trade::count(),
                ],
                'financial' => [
                    'total_deposits' => TransactionRecord::where('type', 'deposit')->sum('amount'),
                    'total_withdrawals' => TransactionRecord::where('type', 'withdrawal')->sum('amount'),
                    'platform_fees' => TransactionRecord::where('type', 'fee')->sum('amount'),
                    'pending_withdrawals' => Order::where('side', 'sell')->where('status', 'pending')->count(),
                ],
                'system' => [
                    'uptime' => '99.9%',
                    'response_time' => '45ms',
                    'error_rate' => '0.01%',
                    'cache_hit_rate' => '94.2%',
                ]
            ];

            return response()->json([
                'success' => true,
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system metrics'
            ], 500);
        }
    }

    /**
     * Get all users with pagination and filtering
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $query = User::with(['wallets']);

            // Apply filters
            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('kyc_status')) {
                $query->where('kyc_status', $request->get('kyc_status'));
            }

            $users = $query->paginate(15);

            // Calculate total balance for each user
            $users->getCollection()->transform(function ($user) {
                $totalBalance = $user->wallets->sum(function ($wallet) {
                    $crypto = Cryptocurrency::where('symbol', $wallet->cryptocurrency_symbol)->first();
                    return $wallet->balance * ($crypto->current_price ?? 1);
                });

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status ?? 'active',
                    'kyc_status' => $user->kyc_status ?? 'pending',
                    'total_balance' => $totalBalance,
                    'created_at' => $user->created_at->format('Y-m-d'),
                    'last_login' => $user->updated_at->format('Y-m-d H:i'),
                ];
            });

            return response()->json([
                'success' => true,
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users'
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function userDetails(int $userId): JsonResponse
    {
        try {
            $user = User::with(['wallets', 'orders', 'trades'])->findOrFail($userId);

            $wallets = $user->wallets->map(function ($wallet) {
                $crypto = Cryptocurrency::where('symbol', $wallet->cryptocurrency_symbol)->first();
                return [
                    'cryptocurrency' => $wallet->cryptocurrency_symbol,
                    'balance' => $wallet->balance,
                    'reserved_balance' => $wallet->reserved_balance,
                    'value_usd' => $wallet->balance * ($crypto->current_price ?? 1),
                ];
            });

            $recentTransactions = TransactionRecord::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status ?? 'active',
                    'kyc_status' => $user->kyc_status ?? 'pending',
                    'created_at' => $user->created_at,
                    'wallets' => $wallets,
                    'total_orders' => $user->orders->count(),
                    'total_trades' => $user->trades->count(),
                    'recent_transactions' => $recentTransactions,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Adjust user balance
     */
    public function adjustBalance(Request $request, int $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cryptocurrency_symbol' => 'required|string|exists:cryptocurrencies,symbol',
            'amount' => 'required|numeric',
            'type' => 'required|in:add,subtract',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($userId);
            $amount = $request->get('amount');
            $type = $request->get('type');
            $reason = $request->get('reason');

            if ($type === 'subtract') {
                $amount = -$amount;
            }

            $result = $this->walletManager->updateBalance(
                $userId,
                $request->get('cryptocurrency_symbol'),
                $amount,
                'admin_adjustment',
                $reason
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Balance adjusted successfully',
                    'new_balance' => $result['new_balance']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust balance'
            ], 500);
        }
    }

    /**
     * Suspend or activate user
     */
    public function toggleUserStatus(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $newStatus = $user->status === 'active' ? 'suspended' : 'active';
            
            $user->update(['status' => $newStatus]);

            // If suspending, revoke all tokens
            if ($newStatus === 'suspended') {
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'message' => "User {$newStatus} successfully",
                'status' => $newStatus
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
        }
    }

    /**
     * Get suspicious activities
     */
    public function suspiciousActivities(): JsonResponse
    {
        try {
            // Mock suspicious activities - implement real detection later
            $activities = [
                [
                    'id' => 1,
                    'user_id' => 1,
                    'user_name' => 'John Doe',
                    'type' => 'Multiple failed logins',
                    'description' => '15 failed login attempts in 10 minutes',
                    'risk_level' => 'high',
                    'created_at' => now()->subHours(2),
                    'status' => 'pending'
                ],
                [
                    'id' => 2,
                    'user_id' => 3,
                    'user_name' => 'Alex Kumar',
                    'type' => 'Large withdrawal',
                    'description' => 'Withdrawal of $50,000 - 10x normal amount',
                    'risk_level' => 'medium',
                    'created_at' => now()->subHours(5),
                    'status' => 'reviewed'
                ]
            ];

            return response()->json([
                'success' => true,
                'activities' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suspicious activities'
            ], 500);
        }
    }

    /**
     * Override cryptocurrency price
     */
    public function overridePrice(Request $request, string $symbol): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'price' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $crypto = Cryptocurrency::where('symbol', $symbol)->firstOrFail();
            $oldPrice = $crypto->current_price;
            $newPrice = $request->input('price');

            $crypto->update([
                'current_price' => $newPrice,
                'last_updated' => now(),
            ]);

            // Log the price override
            TransactionRecord::create([
                'user_id' => $request->user()->id,
                'type' => 'admin_price_override',
                'cryptocurrency_symbol' => $symbol,
                'amount' => $newPrice,
                'description' => "Price override: {$oldPrice} -> {$newPrice}. Reason: " . $request->input('reason'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price overridden successfully',
                'old_price' => $oldPrice,
                'new_price' => $newPrice
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to override price'
            ], 500);
        }
    }

    /**
     * Toggle maintenance mode
     */
    public function maintenanceMode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $enabled = $request->input('enabled');
            $message = $request->input('message', 'System maintenance in progress');

            Cache::put('maintenance_mode', [
                'enabled' => $enabled,
                'message' => $message,
                'activated_by' => $request->user()->name,
                'activated_at' => now(),
            ], now()->addDays(7));

            return response()->json([
                'success' => true,
                'message' => $enabled ? 'Maintenance mode activated' : 'Maintenance mode deactivated',
                'maintenance_mode' => $enabled
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle maintenance mode'
            ], 500);
        }
    }

    /**
     * Get analytics data
     */
    public function analytics(): JsonResponse
    {
        try {
            $analytics = [
                'trading_volume' => [
                    'total' => Trade::sum('quantity'),
                    'monthly' => Trade::where('created_at', '>=', now()->subMonth())->sum('quantity'),
                    'daily' => Trade::whereDate('created_at', today())->sum('quantity'),
                ],
                'user_metrics' => [
                    'total_users' => User::count(),
                    'active_users' => User::where('updated_at', '>=', now()->subDays(30))->count(),
                    'new_users_month' => User::where('created_at', '>=', now()->subMonth())->count(),
                ],
                'revenue_metrics' => [
                    'total_fees' => TransactionRecord::where('type', 'fee')->sum('amount'),
                    'monthly_fees' => TransactionRecord::where('type', 'fee')
                        ->where('created_at', '>=', now()->subMonth())->sum('amount'),
                    'daily_fees' => TransactionRecord::where('type', 'fee')
                        ->whereDate('created_at', today())->sum('amount'),
                ],
                'top_assets' => $this->getTopTradingAssets(),
            ];

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics'
            ], 500);
        }
    }

    // Helper methods
    private function getUserGrowthData(): array
    {
        $months = [];
        $newUsers = [];
        $activeUsers = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M');
            
            $newUsers[] = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            $activeUsers[] = User::where('updated_at', '>=', $date->startOfMonth())
                ->where('updated_at', '<=', $date->endOfMonth())
                ->count();
        }

        return [
            'labels' => $months,
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
        ];
    }

    private function getRevenueData(): array
    {
        $months = [];
        $revenue = [];
        $withdrawals = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M');
            
            $revenue[] = TransactionRecord::where('type', 'fee')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
                
            $withdrawals[] = TransactionRecord::where('type', 'withdrawal')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('amount');
        }

        return [
            'labels' => $months,
            'revenue' => $revenue,
            'withdrawals' => $withdrawals,
        ];
    }

    private function getRecentTransactions(): array
    {
        return TransactionRecord::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'user' => $tx->user->name ?? 'Unknown',
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'cryptocurrency' => $tx->cryptocurrency_symbol,
                    'status' => 'completed',
                    'created_at' => $tx->created_at->diffForHumans(),
                ];
            })->toArray();
    }

    private function getAssetDistribution(): array
    {
        $wallets = Wallet::select('cryptocurrency_symbol', DB::raw('SUM(balance) as total_balance'))
            ->groupBy('cryptocurrency_symbol')
            ->get();

        $distribution = [];
        foreach ($wallets as $wallet) {
            $crypto = Cryptocurrency::where('symbol', $wallet->cryptocurrency_symbol)->first();
            $value = $wallet->total_balance * ($crypto->current_price ?? 1);
            
            $distribution[] = [
                'symbol' => $wallet->cryptocurrency_symbol,
                'balance' => $wallet->total_balance,
                'value_usd' => $value,
            ];
        }

        return $distribution;
    }

    private function getTopTradingAssets(): array
    {
        return Trade::select('cryptocurrency_symbol', DB::raw('COUNT(*) as trade_count'), DB::raw('SUM(quantity) as volume'))
            ->groupBy('cryptocurrency_symbol')
            ->orderBy('trade_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    // ==================== COMPREHENSIVE ADMIN METHODS ====================

    /**
     * Get comprehensive analytics dashboard data
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $analytics = $this->analyticsService->getDashboardAnalytics();

            return response()->json([
                'success' => true,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics'
            ], 500);
        }
    }

    /**
     * Get real-time metrics for live dashboard
     */
    public function getRealTimeMetrics(): JsonResponse
    {
        try {
            $metrics = $this->analyticsService->getRealTimeMetrics();

            return response()->json([
                'success' => true,
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch real-time metrics'
            ], 500);
        }
    }

    // ==================== KYC MANAGEMENT ====================

    /**
     * Get all KYC submissions
     */
    public function getKycSubmissions(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'document_type', 'search']);
            $result = $this->kycService->getKycSubmissions($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC submissions'
            ], 500);
        }
    }

    /**
     * Approve KYC document
     */
    public function approveKyc(Request $request, int $documentId): JsonResponse
    {
        try {
            $result = $this->kycService->approveKyc($documentId, $request->user()->id);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve KYC document'
            ], 500);
        }
    }

    /**
     * Reject KYC document
     */
    public function rejectKyc(Request $request, int $documentId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->kycService->rejectKyc(
                $documentId, 
                $request->user()->id, 
                $request->input('reason')
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject KYC document'
            ], 500);
        }
    }

    /**
     * Get KYC statistics
     */
    public function getKycStatistics(): JsonResponse
    {
        try {
            $stats = $this->kycService->getKycStatistics();

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC statistics'
            ], 500);
        }
    }

    // ==================== SUPPORT TICKETS ====================

    /**
     * Get all support tickets
     */
    public function getSupportTickets(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'category', 'priority', 'assigned_to', 'search']);
            $result = $this->ticketService->getTickets($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch support tickets'
            ], 500);
        }
    }

    /**
     * Assign ticket to admin
     */
    public function assignTicket(Request $request, int $ticketId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'admin_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->ticketService->assignTicket($ticketId, $request->input('admin_id'));

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket'
            ], 500);
        }
    }

    /**
     * Resolve support ticket
     */
    public function resolveTicket(Request $request, int $ticketId): JsonResponse
    {
        try {
            $result = $this->ticketService->resolveTicket(
                $ticketId,
                $request->user()->id,
                $request->input('resolution_message')
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve ticket'
            ], 500);
        }
    }

    // ==================== REFERRAL MANAGEMENT ====================

    /**
     * Get all referral programs
     */
    public function getReferralPrograms(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search']);
            $result = $this->referralService->getReferralPrograms($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referral programs'
            ], 500);
        }
    }

    /**
     * Update commission rate
     */
    public function updateCommissionRate(Request $request, int $programId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'commission_rate' => 'required|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->referralService->updateCommissionRate(
                $programId,
                $request->input('commission_rate')
            );

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update commission rate'
            ], 500);
        }
    }

    // ==================== INVESTMENT MANAGEMENT ====================

    /**
     * Get all investments
     */
    public function getInvestments(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'investment_type', 'cryptocurrency_symbol', 'search']);
            $result = $this->investmentService->getInvestments($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch investments'
            ], 500);
        }
    }

    /**
     * Cancel investment
     */
    public function cancelInvestment(Request $request, int $investmentId): JsonResponse
    {
        try {
            $penaltyRate = $request->input('penalty_rate', 0.1);
            $result = $this->investmentService->cancelInvestment($investmentId, $penaltyRate);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel investment'
            ], 500);
        }
    }

    // ==================== WALLET MANAGEMENT ====================

    /**
     * Get all wallets with filtering
     */
    public function getWallets(Request $request): JsonResponse
    {
        try {
            $query = Wallet::with(['user'])
                ->orderBy('balance', 'desc');

            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('cryptocurrency_symbol')) {
                $query->where('cryptocurrency_symbol', $request->input('cryptocurrency_symbol'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $wallets = $query->paginate(20);

            return response()->json([
                'success' => true,
                'wallets' => $wallets->items(),
                'pagination' => [
                    'current_page' => $wallets->currentPage(),
                    'last_page' => $wallets->lastPage(),
                    'per_page' => $wallets->perPage(),
                    'total' => $wallets->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wallets'
            ], 500);
        }
    }

    // ==================== DEPOSITS & WITHDRAWALS ====================

    /**
     * Get all deposits
     */
    public function getDeposits(Request $request): JsonResponse
    {
        try {
            $query = TransactionRecord::with(['user'])
                ->where('type', 'deposit')
                ->orderBy('created_at', 'desc');

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('cryptocurrency_symbol')) {
                $query->where('cryptocurrency_symbol', $request->input('cryptocurrency_symbol'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $deposits = $query->paginate(20);

            return response()->json([
                'success' => true,
                'deposits' => $deposits->items(),
                'pagination' => [
                    'current_page' => $deposits->currentPage(),
                    'last_page' => $deposits->lastPage(),
                    'per_page' => $deposits->perPage(),
                    'total' => $deposits->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch deposits'
            ], 500);
        }
    }

    /**
     * Get all withdrawals
     */
    public function getWithdrawals(Request $request): JsonResponse
    {
        try {
            $query = TransactionRecord::with(['user'])
                ->where('type', 'withdrawal')
                ->orderBy('created_at', 'desc');

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('cryptocurrency_symbol')) {
                $query->where('cryptocurrency_symbol', $request->input('cryptocurrency_symbol'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->whereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $withdrawals = $query->paginate(20);

            return response()->json([
                'success' => true,
                'withdrawals' => $withdrawals->items(),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch withdrawals'
            ], 500);
        }
    }

    /**
     * Approve withdrawal
     */
    public function approveWithdrawal(int $transactionId): JsonResponse
    {
        try {
            $transaction = TransactionRecord::findOrFail($transactionId);
            
            if ($transaction->type !== 'withdrawal' || $transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid withdrawal transaction'
                ], 400);
            }

            $transaction->update([
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal approved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve withdrawal'
            ], 500);
        }
    }

    /**
     * Reject withdrawal
     */
    public function rejectWithdrawal(Request $request, int $transactionId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = TransactionRecord::findOrFail($transactionId);
            
            if ($transaction->type !== 'withdrawal' || $transaction->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid withdrawal transaction'
                ], 400);
            }

            DB::beginTransaction();

            // Update transaction status
            $transaction->update([
                'status' => 'failed',
                'description' => $transaction->description . ' | Rejected: ' . $request->input('reason'),
                'processed_at' => now(),
            ]);

            // Return funds to user wallet
            $this->walletManager->updateBalance(
                $transaction->user_id,
                $transaction->cryptocurrency_symbol,
                $transaction->amount,
                'withdrawal_rejected',
                'Withdrawal rejected: ' . $request->input('reason')
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal rejected and funds returned'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject withdrawal'
            ], 500);
        }
    }
}