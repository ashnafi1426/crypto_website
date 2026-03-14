<?php

namespace App\Services;

use App\Models\User;
use App\Models\InvestmentPlan;
use App\Models\UserInvestment;
use App\Models\InvestmentDistribution;
use App\Services\Contracts\WalletManagerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvestmentService
{
    private WalletManagerInterface $walletManager;

    public function __construct(WalletManagerInterface $walletManager)
    {
        $this->walletManager = $walletManager;
    }

    /**
     * Get all available investment plans.
     */
    public function getAvailablePlans(string $cryptoType = null): array
    {
        try {
            $query = InvestmentPlan::active();
            
            if ($cryptoType) {
                $query->forCrypto($cryptoType);
            }

            $plans = $query->orderBy('roi_percentage', 'desc')->get();

            $plansData = [];
            foreach ($plans as $plan) {
                $plansData[] = [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'crypto_type' => $plan->crypto_type,
               