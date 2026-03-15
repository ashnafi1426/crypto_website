<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EthereumService
{
    private Client $client;
    private string $nodeUrl;
    private int $confirmationsRequired;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false, // Disable SSL verification for development
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);
        
        $this->nodeUrl = env('ETH_NODE_URL');
        $this->confirmationsRequired = (int) env('ETH_CONFIRMATIONS_REQUIRED', 12);
    }

    /**
     * Make JSON-RPC call to Ethereum node
     */
    private function jsonRpcCall(string $method, array $params = [])
    {
        try {
            $payload = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => 1
            ];

            $response = $this->client->post($this->nodeUrl, [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['error'])) {
                Log::error('Ethereum RPC Error', [
                    'method' => $method,
                    'params' => $params,
                    'error' => $data['error']
                ]);
                return null;
            }

            return $data['result'] ?? null;

        } catch (RequestException $e) {
            Log::error('Ethereum RPC Request Failed', [
                'method' => $method,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get current block number
     */
    public function getCurrentBlockNumber(): ?int
    {
        $result = $this->jsonRpcCall('eth_blockNumber');
        return $result ? hexdec($result) : null;
    }

    /**
     * Get block by number with transactions
     */
    public function getBlockByNumber(int $blockNumber, bool $fullTransactions = true): ?array
    {
        $blockHex = '0x' . dechex($blockNumber);
        return $this->jsonRpcCall('eth_getBlockByNumber', [$blockHex, $fullTransactions]);
    }

    /**
     * Get transaction by hash
     */
    public function getTransactionByHash(string $txHash): ?array
    {
        return $this->jsonRpcCall('eth_getTransactionByHash', [$txHash]);
    }

    /**
     * Get transaction receipt
     */
    public function getTransactionReceipt(string $txHash): ?array
    {
        return $this->jsonRpcCall('eth_getTransactionReceipt', [$txHash]);
    }

    /**
     * Convert Wei to Ether
     */
    public function weiToEther(string $wei): string
    {
        // Remove 0x prefix if present
        $wei = str_replace('0x', '', $wei);
        
        // Convert hex to decimal string
        $weiDecimal = base_convert($wei, 16, 10);
        
        // 1 ETH = 10^18 Wei
        $etherDivisor = '1000000000000000000'; // 10^18
        
        // Use BCMath for precise division
        if (function_exists('bcdiv')) {
            return bcdiv($weiDecimal, $etherDivisor, 18);
        }
        
        // Fallback to regular division (less precise)
        $ether = (float)$weiDecimal / (float)$etherDivisor;
        return number_format($ether, 18, '.', '');
    }

    /**
     * Check if address is valid Ethereum address
     */
    public function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }

    /**
     * Get balance of an address
     */
    public function getBalance(string $address): ?string
    {
        if (!$this->isValidAddress($address)) {
            return null;
        }

        $result = $this->jsonRpcCall('eth_getBalance', [$address, 'latest']);
        
        if ($result) {
            return $this->weiToEther($result);
        }
        
        return null;
    }

    /**
     * Get transactions for a specific address from a block
     */
    public function getTransactionsForAddress(int $blockNumber, string $address): array
    {
        $block = $this->getBlockByNumber($blockNumber, true);
        
        if (!$block || !isset($block['transactions'])) {
            return [];
        }

        $transactions = [];
        $address = strtolower($address);

        foreach ($block['transactions'] as $tx) {
            // Check if transaction is sent TO our address
            if (isset($tx['to']) && strtolower($tx['to']) === $address) {
                $transactions[] = [
                    'hash' => $tx['hash'],
                    'from' => $tx['from'],
                    'to' => $tx['to'],
                    'value' => $this->weiToEther($tx['value']),
                    'blockNumber' => hexdec($tx['blockNumber']),
                    'transactionIndex' => hexdec($tx['transactionIndex']),
                    'gas' => hexdec($tx['gas']),
                    'gasPrice' => $tx['gasPrice'],
                    'input' => $tx['input']
                ];
            }
        }

        return $transactions;
    }

    /**
     * Calculate confirmations for a transaction
     */
    public function getConfirmations(int $txBlockNumber): int
    {
        $currentBlock = $this->getCurrentBlockNumber();
        
        if (!$currentBlock || $txBlockNumber > $currentBlock) {
            return 0;
        }
        
        return $currentBlock - $txBlockNumber + 1;
    }

    /**
     * Check if transaction has enough confirmations
     */
    public function hasEnoughConfirmations(int $txBlockNumber): bool
    {
        return $this->getConfirmations($txBlockNumber) >= $this->confirmationsRequired;
    }

    /**
     * Get the last processed block number from cache
     */
    public function getLastProcessedBlock(): int
    {
        return Cache::get('eth_last_processed_block', $this->getCurrentBlockNumber() ?? 0);
    }

    /**
     * Set the last processed block number in cache
     */
    public function setLastProcessedBlock(int $blockNumber): void
    {
        Cache::put('eth_last_processed_block', $blockNumber, now()->addDays(7));
    }

    /**
     * Test connection to Ethereum node
     */
    public function testConnection(): bool
    {
        $blockNumber = $this->getCurrentBlockNumber();
        return $blockNumber !== null;
    }

    /**
     * Get network information
     */
    public function getNetworkInfo(): array
    {
        $blockNumber = $this->getCurrentBlockNumber();
        $chainId = $this->jsonRpcCall('eth_chainId');
        
        return [
            'connected' => $blockNumber !== null,
            'current_block' => $blockNumber,
            'chain_id' => $chainId ? hexdec($chainId) : null,
            'confirmations_required' => $this->confirmationsRequired,
            'node_url' => $this->nodeUrl
        ];
    }
}