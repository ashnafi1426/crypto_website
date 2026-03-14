<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use App\Models\Trade;
use App\Models\TransactionRecord;
use App\Models\Cryptocurrency;
use App\Models\Investment;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    /**
     * Get comprehensive analytics dashboard data
     */
    public function getDashboardAnalytics(): array
    {
        return Cache::remember('admin_analytics_dashboard', 300, function () {
            return [
                'overview' => $this->getOverviewMetrics(),
                'trading_analytics' => $this->getTradingAnalytics(),
                'user_analytics' => $this->getUserAnalytics(),
                'financial_analytics' => $this->getFinancialAnalytics(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'growth_trends' => $this->getGrowthTrends(),
            ];
        });
    }

    /**
     * Get overview metrics
     */
    private function getOverviewMetrics(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'total_users' => [
                'value' => User::count(),
                'change' => $this->calculatePercentageChange(
                    User::where('created_at', '>=', $lastMonth)->where('created_at', '<', $thisMonth)->count(),
                    User::where('created_at', '>=', $thisMonth)->count()
                )
            ],
            'total_trades' => [
                'value' => Trade::count(),
                'change' => $this->calculatePercentageChange(
                    Trade::whereDate('created_at', $yesterday)->count(),
                    Trade::whereDate('created_at', $today)->count()
                )
            ],
            'total_volume' => [
                'value' => Trade::sum('total_amount'),
                'change' => $this->calculatePercentageChange(
                    Trade::whereDate('created_at', $yesterday)->sum('total_amount'),
                    Trade::whereDate('created_at', $today)->sum('total_amount')
                )
            ],
            'platform_revenue' => [
                'value' => TransactionRecord::where('type', 'fee')->sum('amount'),
                'change' => $this->calculatePercentageChange(
                    TransactionRecord::where('type', 'fee')->whereDate('created_at', $yesterday)->sum('amount'),
                    TransactionRecord::where('type', 'fee')->whereDate('created_at', $today)->sum('amount')
                )
            ],
        ];
    }

    /**
     * Get trading analytics
     */
    private function getTradingAnalytics(): array
    {
        return [
            'daily_volume' => $this->getDailyTradingVolume(),
            'top_trading_pairs' => $this->getTopTradingPairs(),
            'order_book_depth' => $this->getOrderBookDepth(),
            'trade_distribution' => $this->getTradeDistribution(),
            'average_trade_size' => Trade::avg('total_amount'),
            'total_orders' => Order::count(),
            'filled_orders' => Order::where('status', 'filled')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Get user analytics
     */
    private function getUserAnalytics(): array
    {
        return [
            'user_growth' => $this->getUserGrowthData(),
            'user_activity' => $this->getUserActivityData(),
            'user_segments' => $this->getUserSegments(),
            'kyc_statistics' => $this->getKycStatistics(),
            'geographic_distribution' => $this->getGeographicDistribution(),
            'user_retention' => $this->getUserRetentionRate(),
        ];
    }

    /**
     * Get financial analytics
     */
    private function getFinancialAnalytics(): array
    {
        return [
            'revenue_breakdown' => $this->getRevenueBreakdown(),
            'deposit_withdrawal_trends' => $this->getDepositWithdrawalTrends(),
            'fee_analysis' => $this->getFeeAnalysis(),
            'profit_margins' => $this->getProfitMargins(),
            'cash_flow' => $this->getCashFlowData(),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'system_uptime' => 99.97, // Mock data - implement real monitoring
            'average_response_time' => 45, // milliseconds
            'error_rate' => 0.01, // percentage
            'cache_hit_rate' => 94.2, // percentage
            'database_performance' => $this->getDatabasePerformance(),
            'api_performance' => $this->getApiPerformance(),
        ];
    }

    /**
     * Get growth trends
     */
    private function getGrowthTrends(): array
    {
        return [
            'monthly_growth' => $this->getMonthlyGrowthData(),
            'quarterly_comparison' => $this->getQuarterlyComparison(),
            'year_over_year' => $this->getYearOverYearGrowth(),
            'forecasting' => $this->getGrowthForecasting(),
        ];
    }

    /**
     * Get daily trading volume for the last 30 days
     */
    private function getDailyTradingVolume(): array
    {
        $data = Trade::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as volume'),
                DB::raw('COUNT(*) as trade_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->toArray(),
            'volume' => $data->pluck('volume')->toArray(),
            'trade_count' => $data->pluck('trade_count')->toArray(),
        ];
    }

    /**
     * Get top trading pairs
     */
    private function getTopTradingPairs(): array
    {
        return Trade::select(
                'cryptocurrency_symbol',
                DB::raw('COUNT(*) as trade_count'),
                DB::raw('SUM(total_amount) as volume'),
                DB::raw('AVG(price) as avg_price')
            )
            ->groupBy('cryptocurrency_symbol')
            ->orderBy('volume', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get order book depth analysis
     */
    private function getOrderBookDepth(): array
    {
        $buyOrders = Order::where('side', 'buy')->where('status', 'pending')->sum('quantity');
        $sellOrders = Order::where('side', 'sell')->where('status', 'pending')->sum('quantity');

        return [
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
            'total_depth' => $buyOrders + $sellOrders,
            'buy_sell_ratio' => $sellOrders > 0 ? $buyOrders / $sellOrders : 0,
        ];
    }

    /**
     * Get trade distribution by size
     */
    private function getTradeDistribution(): array
    {
        return [
            'small_trades' => Trade::where('total_amount', '<', 100)->count(),
            'medium_trades' => Trade::whereBetween('total_amount', [100, 1000])->count(),
            'large_trades' => Trade::whereBetween('total_amount', [1000, 10000])->count(),
            'whale_trades' => Trade::where('total_amount', '>', 10000)->count(),
        ];
    }

    /**
     * Get user growth data for the last 12 months
     */
    private function getUserGrowthData(): array
    {
        $months = [];
        $newUsers = [];
        $totalUsers = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = $date->format('M Y');
            
            $newUsers[] = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
                
            $totalUsers[] = User::where('created_at', '<=', $date->endOfMonth())->count();
        }

        return [
            'labels' => $months,
            'new_users' => $newUsers,
            'total_users' => $totalUsers,
        ];
    }

    /**
     * Get user activity data
     */
    private function getUserActivityData(): array
    {
        $activeToday = User::where('updated_at', '>=', now()->startOfDay())->count();
        $activeWeek = User::where('updated_at', '>=', now()->subWeek())->count();
        $activeMonth = User::where('updated_at', '>=', now()->subMonth())->count();

        return [
            'daily_active_users' => $activeToday,
            'weekly_active_users' => $activeWeek,
            'monthly_active_users' => $activeMonth,
            'activity_ratio' => [
                'daily' => User::count() > 0 ? ($activeToday / User::count()) * 100 : 0,
                'weekly' => User::count() > 0 ? ($activeWeek / User::count()) * 100 : 0,
                'monthly' => User::count() > 0 ? ($activeMonth / User::count()) * 100 : 0,
            ]
        ];
    }

    /**
     * Get user segments
     */
    private function getUserSegments(): array
    {
        return [
            'new_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'active_traders' => User::whereHas('trades', function($q) {
                $q->where('created_at', '>=', now()->subDays(30));
            })->count(),
            'investors' => 0, // No investments table yet
            'high_value_users' => User::whereHas('wallets', function($q) {
                $q->havingRaw('SUM(balance) > 10000');
            })->count(),
        ];
    }

    /**
     * Get KYC statistics
     */
    private function getKycStatistics(): array
    {
        return [
            'total_submissions' => User::whereNotNull('kyc_status')->count(),
            'approved' => User::where('kyc_status', 'approved')->count(),
            'pending' => User::where('kyc_status', 'pending')->count(),
            'rejected' => User::where('kyc_status', 'rejected')->count(),
        ];
    }

    /**
     * Get geographic distribution (mock data)
     */
    private function getGeographicDistribution(): array
    {
        return [
            'United States' => 35,
            'United Kingdom' => 20,
            'Germany' => 15,
            'Japan' => 12,
            'Canada' => 8,
            'Australia' => 5,
            'Others' => 5,
        ];
    }

    /**
     * Get user retention rate
     */
    private function getUserRetentionRate(): float
    {
        $totalUsers = User::count();
        if ($totalUsers === 0) return 0;

        $activeUsers = User::where('updated_at', '>=', now()->subDays(30))->count();
        return round(($activeUsers / $totalUsers) * 100, 2);
    }

    /**
     * Get revenue breakdown
     */
    private function getRevenueBreakdown(): array
    {
        return [
            'trading_fees' => TransactionRecord::where('type', 'fee')->sum('amount'),
            'withdrawal_fees' => 0, // No fee column in transaction_records yet
            'listing_fees' => 0, // Implement when listing feature is added
            'premium_subscriptions' => 0, // Implement when subscription feature is added
        ];
    }

    /**
     * Get deposit/withdrawal trends
     */
    private function getDepositWithdrawalTrends(): array
    {
        $days = [];
        $deposits = [];
        $withdrawals = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $days[] = $date;
            
            $deposits[] = TransactionRecord::where('type', 'deposit')
                ->whereDate('created_at', $date)
                ->sum('amount');
                
            $withdrawals[] = TransactionRecord::where('type', 'withdrawal')
                ->whereDate('created_at', $date)
                ->sum('amount');
        }

        return [
            'labels' => $days,
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
        ];
    }

    /**
     * Get fee analysis
     */
    private function getFeeAnalysis(): array
    {
        $totalFees = TransactionRecord::where('type', 'fee')->sum('amount');
        $totalVolume = Trade::sum('total_amount');

        return [
            'total_fees_collected' => $totalFees,
            'average_fee_rate' => $totalVolume > 0 ? ($totalFees / $totalVolume) * 100 : 0,
            'fees_by_crypto' => TransactionRecord::where('type', 'fee')
                ->select('cryptocurrency_symbol', DB::raw('SUM(amount) as total_fees'))
                ->groupBy('cryptocurrency_symbol')
                ->get()
                ->pluck('total_fees', 'cryptocurrency_symbol')
                ->toArray(),
        ];
    }

    /**
     * Get profit margins
     */
    private function getProfitMargins(): array
    {
        $revenue = TransactionRecord::where('type', 'fee')->sum('amount');
        $costs = 0; // Implement cost tracking

        return [
            'gross_revenue' => $revenue,
            'operating_costs' => $costs,
            'net_profit' => $revenue - $costs,
            'profit_margin' => $revenue > 0 ? (($revenue - $costs) / $revenue) * 100 : 0,
        ];
    }

    /**
     * Get cash flow data
     */
    private function getCashFlowData(): array
    {
        $inflow = TransactionRecord::whereIn('type', ['deposit', 'fee'])->sum('amount');
        $outflow = TransactionRecord::where('type', 'withdrawal')->sum('amount');

        return [
            'total_inflow' => $inflow,
            'total_outflow' => $outflow,
            'net_cash_flow' => $inflow - $outflow,
            'cash_flow_ratio' => $outflow > 0 ? $inflow / $outflow : 0,
        ];
    }

    /**
     * Get database performance metrics
     */
    private function getDatabasePerformance(): array
    {
        return [
            'query_time' => 12.5, // milliseconds (mock data)
            'connection_count' => 45,
            'slow_queries' => 2,
            'index_efficiency' => 98.5,
        ];
    }

    /**
     * Get API performance metrics
     */
    private function getApiPerformance(): array
    {
        return [
            'requests_per_minute' => 1250,
            'success_rate' => 99.8,
            'average_response_time' => 85, // milliseconds
            'rate_limit_hits' => 12,
        ];
    }

    /**
     * Get monthly growth data
     */
    private function getMonthlyGrowthData(): array
    {
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'user_growth' => $this->calculatePercentageChange(
                User::where('created_at', '>=', $lastMonth)->where('created_at', '<', $currentMonth)->count(),
                User::where('created_at', '>=', $currentMonth)->count()
            ),
            'volume_growth' => $this->calculatePercentageChange(
                Trade::where('created_at', '>=', $lastMonth)->where('created_at', '<', $currentMonth)->sum('total_amount'),
                Trade::where('created_at', '>=', $currentMonth)->sum('total_amount')
            ),
            'revenue_growth' => $this->calculatePercentageChange(
                TransactionRecord::where('type', 'fee')->where('created_at', '>=', $lastMonth)->where('created_at', '<', $currentMonth)->sum('amount'),
                TransactionRecord::where('type', 'fee')->where('created_at', '>=', $currentMonth)->sum('amount')
            ),
        ];
    }

    /**
     * Get quarterly comparison
     */
    private function getQuarterlyComparison(): array
    {
        $currentQuarter = now()->startOfQuarter();
        $lastQuarter = now()->subQuarter()->startOfQuarter();

        return [
            'current_quarter' => [
                'users' => User::where('created_at', '>=', $currentQuarter)->count(),
                'volume' => Trade::where('created_at', '>=', $currentQuarter)->sum('total_amount'),
                'revenue' => TransactionRecord::where('type', 'fee')->where('created_at', '>=', $currentQuarter)->sum('amount'),
            ],
            'last_quarter' => [
                'users' => User::where('created_at', '>=', $lastQuarter)->where('created_at', '<', $currentQuarter)->count(),
                'volume' => Trade::where('created_at', '>=', $lastQuarter)->where('created_at', '<', $currentQuarter)->sum('total_amount'),
                'revenue' => TransactionRecord::where('type', 'fee')->where('created_at', '>=', $lastQuarter)->where('created_at', '<', $currentQuarter)->sum('amount'),
            ],
        ];
    }

    /**
     * Get year over year growth
     */
    private function getYearOverYearGrowth(): array
    {
        $currentYear = now()->startOfYear();
        $lastYear = now()->subYear()->startOfYear();

        return [
            'user_growth' => $this->calculatePercentageChange(
                User::where('created_at', '>=', $lastYear)->where('created_at', '<', $currentYear)->count(),
                User::where('created_at', '>=', $currentYear)->count()
            ),
            'volume_growth' => $this->calculatePercentageChange(
                Trade::where('created_at', '>=', $lastYear)->where('created_at', '<', $currentYear)->sum('total_amount'),
                Trade::where('created_at', '>=', $currentYear)->sum('total_amount')
            ),
            'revenue_growth' => $this->calculatePercentageChange(
                TransactionRecord::where('type', 'fee')->where('created_at', '>=', $lastYear)->where('created_at', '<', $currentYear)->sum('amount'),
                TransactionRecord::where('type', 'fee')->where('created_at', '>=', $currentYear)->sum('amount')
            ),
        ];
    }

    /**
     * Get growth forecasting (simple linear projection)
     */
    private function getGrowthForecasting(): array
    {
        // Simple linear regression for next 3 months (mock implementation)
        return [
            'projected_users' => User::count() * 1.15, // 15% growth projection
            'projected_volume' => Trade::sum('total_amount') * 1.20, // 20% growth projection
            'projected_revenue' => TransactionRecord::where('type', 'fee')->sum('amount') * 1.18, // 18% growth projection
        ];
    }

    /**
     * Calculate percentage change
     */
    private function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Get real-time metrics (for live dashboard updates)
     */
    public function getRealTimeMetrics(): array
    {
        return [
            'active_users_now' => User::where('updated_at', '>=', now()->subMinutes(5))->count(),
            'trades_last_hour' => Trade::where('created_at', '>=', now()->subHour())->count(),
            'volume_last_hour' => Trade::where('created_at', '>=', now()->subHour())->sum('total_amount'),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'system_load' => rand(20, 80), // Mock system load percentage
        ];
    }
}