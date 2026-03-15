<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EthereumService;
use App\Services\BlockchainDepositService;
use App\Models\DepositAddress;
use App\Models\Deposit;
use Illuminate\Support\Facades\Log;

class CheckEthereumDeposits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ethereum:check-deposits 
                            {--blocks=10 : Number of blocks to scan}
                            {--force : Force scan from current block}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Ethereum blockchain for deposits to user addresses';

    private EthereumService $ethereumService;
    private BlockchainDepositService $depositService;

    public function __construct(EthereumService $ethereumService, BlockchainDepositService $depositService)
    {
        parent::__construct();
        $this->ethereumService = $ethereumService;
        $this->depositService = $depositService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting Ethereum deposit monitoring...');
        
        // Test connection first
        if (!$this->ethereumService->testConnection()) {
            $this->error('❌ Failed to connect to Ethereum node');
            return 1;
        }

        $networkInfo = $this->ethereumService->getNetworkInfo();
        $this->info("✅ Connected to Ethereum network");
        $this->info("   Current Block: {$networkInfo['current_block']}");
        $this->info("   Chain ID: {$networkInfo['chain_id']}");
        $this->info("   Confirmations Required: {$networkInfo['confirmations_required']}");

        // Get scan parameters
        $blocksToScan = (int) $this->option('blocks');
        $force = $this->option('force');
        
        $currentBlock = $networkInfo['current_block'];
        $lastProcessedBlock = $force ? $currentBlock : $this->ethereumService->getLastProcessedBlock();
        
        // Determine scan range
        if ($force) {
            $startBlock = $currentBlock - $blocksToScan + 1;
            $endBlock = $currentBlock;
        } else {
            $startBlock = max($lastProcessedBlock + 1, $currentBlock - $blocksToScan);
            $endBlock = $currentBlock;
        }

        if ($startBlock > $endBlock) {
            $this->info('📋 No new blocks to process');
            return 0;
        }

        $this->info("🔄 Scanning blocks {$startBlock} to {$endBlock}");

        // Get all active deposit addresses
        $depositAddresses = DepositAddress::where('is_active', true)
            ->where('currency', 'ETH')
            ->get();

        if ($depositAddresses->isEmpty()) {
            $this->info('📋 No active ETH deposit addresses found');
            return 0;
        }

        $this->info("👥 Monitoring {$depositAddresses->count()} deposit addresses");

        $totalDepositsFound = 0;
        $totalDepositsProcessed = 0;

        // Scan each block
        for ($blockNumber = $startBlock; $blockNumber <= $endBlock; $blockNumber++) {
            $this->info("🔍 Scanning block {$blockNumber}...");
            
            try {
                // Check each deposit address for transactions in this block
                foreach ($depositAddresses as $depositAddress) {
                    $transactions = $this->ethereumService->getTransactionsForAddress(
                        $blockNumber, 
                        $depositAddress->address
                    );

                    foreach ($transactions as $tx) {
                        $totalDepositsFound++;
                        
                        $this->info("💰 Deposit found!");
                        $this->info("   Address: {$depositAddress->address}");
                        $this->info("   Amount: {$tx['value']} ETH");
                        $this->info("   TX Hash: {$tx['hash']}");
                        $this->info("   From: {$tx['from']}");

                        // Process the deposit
                        $result = $this->depositService->processEthereumDeposit(
                            $depositAddress,
                            $tx
                        );

                        if ($result['success']) {
                            $totalDepositsProcessed++;
                            $this->info("   ✅ Deposit processed successfully");
                            
                            if ($result['confirmed']) {
                                $this->info("   🎉 Deposit confirmed and credited to user");
                            } else {
                                $confirmations = $this->ethereumService->getConfirmations($tx['blockNumber']);
                                $this->info("   ⏳ Waiting for confirmations ({$confirmations}/{$networkInfo['confirmations_required']})");
                            }
                        } else {
                            $this->error("   ❌ Failed to process deposit: {$result['message']}");
                        }
                    }
                }

                // Update last processed block
                $this->ethereumService->setLastProcessedBlock($blockNumber);

            } catch (\Exception $e) {
                $this->error("❌ Error scanning block {$blockNumber}: {$e->getMessage()}");
                Log::error('Ethereum deposit scan error', [
                    'block' => $blockNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Check for confirmation updates on pending deposits
        $this->info("🔄 Checking pending deposits for confirmations...");
        $confirmedCount = $this->depositService->updatePendingDeposits();
        
        if ($confirmedCount > 0) {
            $this->info("✅ {$confirmedCount} deposits confirmed and credited");
        }

        // Summary
        $this->info('📊 Scan Summary:');
        $this->info("   Blocks Scanned: " . ($endBlock - $startBlock + 1));
        $this->info("   Deposits Found: {$totalDepositsFound}");
        $this->info("   Deposits Processed: {$totalDepositsProcessed}");
        $this->info("   Deposits Confirmed: {$confirmedCount}");
        $this->info('✅ Ethereum deposit monitoring completed');

        return 0;
    }
}
