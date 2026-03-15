<?php
namespace App\Services;

use App\Models\DepositAddress;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WalletAddressService
{
    private AdminWalletService $adminWalletService;
    
    public function __construct(AdminWalletService $adminWalletService)
    {
        $this->adminWalletService = $adminWalletService;
    }

    /**
     * Supported cryptocurrencies and their networks
     */
    private const SUPPORTED_CURRENCIES = [
        'BTC' => ['Bitcoin'],
        'ETH' => ['Ethereum'],
        'USDT' => ['Ethereum'],
        'USDC' => ['Ethereum'],
        'BNB' => ['BSC'],
        'SOL' => ['Solana'],
        'XRP' => ['Ripple'],
        'ADA' => ['Cardano'],
        'DOT' => ['Polkadot'],
        'MATIC' => ['Polygon']
    ];

    /**
     * Get or create deposit address for user
     */
    public function getDepositAddress(User $user, string $currency, string $network): ?DepositAddress
    {
        try {
            $currency = strtoupper($currency);
            
            // Validate currency and network
            if (!$this->isValidCurrencyNetwork($currency, $network)) {
                throw new \InvalidArgumentException("Invalid currency/network combination: {$currency}/{$network}");
            }

            // Check if we should use admin wallet for direct collection
            if ($this->adminWalletService->shouldUseAdminWallet($currency)) {
                return $this->getAdminDepositAddress($user, $currency, $network);
            }

            // Try to get existing user-specific address first
            $depositAddress = DepositAddress::where('user_id', $user->id)
                ->where('currency', $currency)
                ->where('network', $network)
                ->where('is_active', true)
                ->first();

            if ($depositAddress) {
                $depositAddress->touch('last_used_at');
                return $depositAddress;
            }

            // Generate individual user address (for forwarding mode or when admin wallet disabled)
            $address = $this->generateMockAddress($currency, $network);

            // Create new deposit address
            $depositAddress = DepositAddress::create([
                'user_id' => $user->id,
                'currency' => $currency,
                'network' => $network,
                'address' => $address,
                'type' => 'user_generated',
                'is_active' => true,
                'last_used_at' => now()
            ]);

            Log::info("Generated user deposit address for user {$user->id}: {$currency}/{$network} - {$address}");

            return $depositAddress;

        } catch (\Exception $e) {
            Log::error("Failed to get deposit address: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get admin wallet address for direct collection
     */
    private function getAdminDepositAddress(User $user, string $currency, string $network): ?DepositAddress
    {
        $adminAddress = $this->adminWalletService->getAdminWalletAddress($currency);
        
        if (!$adminAddress) {
            Log::warning("Admin wallet not configured for {$currency}, falling back to user address");
            return $this->getDepositAddress($user, $currency, $network);
        }

        // Check if we already have this admin address stored for the user
        $depositAddress = DepositAddress::where('user_id', $user->id)
            ->where('currency', $currency)
            ->where('network', $network)
            ->where('address', $adminAddress)
            ->where('is_active', true)
            ->first();

        if ($depositAddress) {
            $depositAddress->touch('last_used_at');
            return $depositAddress;
        }

        // Create new admin deposit address record
        $depositAddress = DepositAddress::create([
            'user_id' => $user->id,
            'currency' => $currency,
            'network' => $network,
            'address' => $adminAddress,
            'type' => 'admin_treasury',
            'is_active' => true,
            'last_used_at' => now(),
            'metadata' => json_encode([
                'collection_mode' => 'direct',
                'admin_wallet' => true,
                'created_at' => now()->toISOString()
            ])
        ]);

        Log::info("Assigned admin treasury address to user {$user->id}: {$currency}/{$network} - {$adminAddress}");

        return $depositAddress;
    }

    /**
     * Generate mock address for demo (replace with real wallet generation)
     */
    private function generateMockAddress(string $currency, string $network): string
    {
        // These are example addresses - in production you'd generate real ones
        $mockAddresses = [
            'BTC' => [
                'Bitcoin' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'
            ],
            'ETH' => [
                'Ethereum' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B',
                'BSC' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B'
            ],
            'USDT' => [
                'Ethereum' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B',
                'BSC' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B',
                'Tron' => 'TRX9a5u6S8L2fPtXrNvR6aJ8kQm3B7c4D5e6F7g8H9'
            ],
            'BNB' => [
                'BSC' => '0x742d35Cc6634C0532925a3b8D4C2C4e07C8B8C8B',
                'BEP2' => 'bnb1xy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh'
            ]
        ];

        return $mockAddresses[$currency][$network] ?? $this->generateRandomAddress($currency);
    }

    /**
     * Generate random address for unsupported combinations
     */
    private function generateRandomAddress(string $currency): string
    {
        switch ($currency) {
            case 'BTC':
                return 'bc1q' . bin2hex(random_bytes(20));
            case 'ETH':
            case 'USDT':
            case 'USDC':
            case 'BNB':
                return '0x' . bin2hex(random_bytes(20));
            default:
                return bin2hex(random_bytes(25));
        }
    }

    /**
     * Validate currency and network combination
     */
    private function isValidCurrencyNetwork(string $currency, string $network): bool
    {
        return isset(self::SUPPORTED_CURRENCIES[$currency]) && 
               in_array($network, self::SUPPORTED_CURRENCIES[$currency]);
    }

    /**
     * Get all supported currencies
     */
    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Get networks for a specific currency
     */
    public function getNetworksForCurrency(string $currency): array
    {
        $currency = strtoupper($currency);
        return self::SUPPORTED_CURRENCIES[$currency] ?? [];
    }

    /**
     * Store real MetaMask address for user (optimized for performance)
     */
    public function storeMetaMaskAddress(User $user, string $currency, string $network, string $address): DepositAddress
    {
        // Quick validation without expensive regex
        if (strlen($address) !== 42 || !str_starts_with($address, '0x')) {
            throw new \InvalidArgumentException("Invalid address format for {$currency}/{$network}");
        }

        $currency = strtoupper($currency);

        // Use updateOrCreate for better performance (single query)
        return DepositAddress::updateOrCreate(
            [
                'user_id' => $user->id,
                'currency' => $currency,
                'network' => $network,
            ],
            [
                'address' => $address,
                'is_active' => true,
                'last_used_at' => now()
            ]
        );
    }

    /**
     * Validate address format
     */
    private function validateAddressFormat(string $address, string $currency, string $network): bool
    {
        switch ($currency) {
            case 'BTC':
                return preg_match('/^(bc1|[13])[a-zA-HJ-NP-Z0-9]{25,62}$/', $address);
            case 'ETH':
            case 'USDT':
            case 'USDC':
            case 'BNB':
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
            default:
                return strlen($address) > 20; // Basic length check
        }
    }

    /**
     * Get deposit instructions including admin wallet info
     */
    public function getDepositInstructions(string $currency): array
    {
        return $this->adminWalletService->getDepositInstructions($currency);
    }
}