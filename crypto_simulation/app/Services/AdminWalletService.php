<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AdminWalletService
{
    /**
     * Get admin wallet address for a specific currency
     */
    public function getAdminWalletAddress(string $currency): ?string
    {
        $currency = strtoupper($currency);
        
        // Check if admin wallets are enabled
        if (!env('ADMIN_WALLET_ENABLED', false)) {
            return null;
        }
        
        $envKey = "ADMIN_WALLET_{$currency}";
        $address = env($envKey);
        
        if (!$address) {
            Log::warning("No admin wallet configured for currency: {$currency}");
            return null;
        }
        
        // Validate address format
        if (!$this->validateWalletAddress($address, $currency)) {
            Log::error("Invalid admin wallet address for {$currency}: {$address}");
            return null;
        }
        
        return $address;
    }
    
    /**
     * Get all configured admin wallets
     */
    public function getAllAdminWallets(): array
    {
        $currencies = ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'SOL', 'XRP', 'ADA', 'DOT', 'MATIC'];
        $wallets = [];
        
        foreach ($currencies as $currency) {
            $address = $this->getAdminWalletAddress($currency);
            if ($address) {
                $wallets[$currency] = [
                    'currency' => $currency,
                    'address' => $address,
                    'network' => $this->getNetworkForCurrency($currency),
                    'status' => 'active',
                    'last_updated' => now()->toISOString()
                ];
            }
        }
        
        return $wallets;
    }
    
    /**
     * Check if admin wallet collection is enabled
     */
    public function isAdminWalletEnabled(): bool
    {
        return env('ADMIN_WALLET_ENABLED', false);
    }
    
    /**
     * Get collection mode (direct or forwarding)
     */
    public function getCollectionMode(): string
    {
        return env('ADMIN_WALLET_COLLECTION_MODE', 'direct');
    }
    
    /**
     * Validate wallet address format
     */
    private function validateWalletAddress(string $address, string $currency): bool
    {
        switch ($currency) {
            case 'BTC':
                // Bitcoin address validation (Legacy, SegWit, Bech32)
                return preg_match('/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address);
                
            case 'ETH':
            case 'USDT':
            case 'USDC':
            case 'BNB':
                // Ethereum-based address validation
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
                
            case 'SOL':
                // Solana address validation
                return preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
                
            case 'XRP':
                // Ripple address validation
                return preg_match('/^r[1-9A-HJ-NP-Za-km-z]{25,34}$/', $address);
                
            case 'ADA':
                // Cardano address validation
                return preg_match('/^addr1[a-z0-9]{98}$/', $address) || 
                       preg_match('/^DdzFF[a-zA-Z0-9]{93}$/', $address);
                
            case 'DOT':
                // Polkadot address validation
                return preg_match('/^1[a-zA-Z0-9]{46}$/', $address);
                
            case 'MATIC':
                // Polygon (same as Ethereum)
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
                
            default:
                // Basic length check for unknown currencies
                return strlen($address) > 20 && strlen($address) < 100;
        }
    }
    
    /**
     * Get primary network for currency
     */
    private function getNetworkForCurrency(string $currency): string
    {
        $networks = [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum',
            'USDT' => 'Ethereum',
            'USDC' => 'Ethereum',
            'BNB' => 'BSC',
            'SOL' => 'Solana',
            'XRP' => 'Ripple',
            'ADA' => 'Cardano',
            'DOT' => 'Polkadot',
            'MATIC' => 'Polygon'
        ];
        
        return $networks[$currency] ?? 'Unknown';
    }
    
    /**
     * Get admin wallet statistics
     */
    public function getAdminWalletStatistics(): array
    {
        $wallets = $this->getAllAdminWallets();
        
        return [
            'total_wallets' => count($wallets),
            'enabled_currencies' => array_keys($wallets),
            'collection_mode' => $this->getCollectionMode(),
            'status' => $this->isAdminWalletEnabled() ? 'active' : 'disabled',
            'wallets' => $wallets,
            'configuration' => [
                'auto_collection' => $this->getCollectionMode() === 'direct',
                'manual_forwarding' => $this->getCollectionMode() === 'forwarding',
                'last_check' => now()->toISOString()
            ]
        ];
    }
    
    /**
     * Update admin wallet address (for admin panel)
     */
    public function updateAdminWallet(string $currency, string $address): bool
    {
        $currency = strtoupper($currency);
        
        // Validate address
        if (!$this->validateWalletAddress($address, $currency)) {
            throw new \InvalidArgumentException("Invalid wallet address format for {$currency}");
        }
        
        // In production, you would update this in a database or config file
        // For now, we'll log the change and cache it
        Cache::put("admin_wallet_{$currency}", $address, 3600);
        
        Log::info("Admin wallet updated for {$currency}: {$address}");
        
        return true;
    }
    
    /**
     * Check if a deposit should go to admin wallet
     */
    public function shouldUseAdminWallet(string $currency): bool
    {
        return $this->isAdminWalletEnabled() && 
               $this->getAdminWalletAddress($currency) !== null &&
               $this->getCollectionMode() === 'direct';
    }
    
    /**
     * Get deposit instructions for admin wallet
     */
    public function getDepositInstructions(string $currency): array
    {
        $adminAddress = $this->getAdminWalletAddress($currency);
        
        if (!$adminAddress) {
            return [
                'type' => 'user_wallet',
                'message' => 'Individual wallet will be generated'
            ];
        }
        
        return [
            'type' => 'admin_wallet',
            'address' => $adminAddress,
            'network' => $this->getNetworkForCurrency($currency),
            'message' => 'Send directly to exchange wallet',
            'note' => 'All deposits are collected in our secure treasury wallet',
            'collection_mode' => $this->getCollectionMode()
        ];
    }
}