<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Trade;
use App\Models\Order;
use App\Models\TransactionRecord;
use App\Models\KycDocument;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\ReferralProgram;
use App\Models\Investment;
use App\Models\Cryptocurrency;
use Illuminate\Support\Str;

class AdminTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('is_admin', false)->get();
        $cryptos = Cryptocurrency::all();
        $admin = User::where('is_admin', true)->first();

        // Create sample trades
        $this->createSampleTrades($users, $cryptos);
        
        // Create sample orders
        $this->createSampleOrders($users, $cryptos);
        
        // Create sample transaction records
        $this->createSampleTransactions($users, $cryptos);
        
        // Create sample KYC documents
        $this->createSampleKycDocuments($users);
        
        // Create sample support tickets
        $this->createSampleSupportTickets($users, $admin);
        
        // Create sample referral programs
        $this->createSampleReferralPrograms($users);
        
        // Create sample investments
        $this->createSampleInvestments($users, $cryptos);

        echo "✅ All admin test data created successfully!\n";
    }

    private function createSampleTrades($users, $cryptos): void
    {
        $tradeTypes = ['buy', 'sell'];
        
        for ($i = 0; $i < 100; $i++) {
            $user = $users->random();
            $crypto = $cryptos->random();
            $side = $tradeTypes[array_rand($tradeTypes)];
            $quantity = rand(1, 1000) / 100;
            $price = $crypto->current_price * (rand(95, 105) / 100); // ±5% of current price
            
            Trade::create([
                'user_id' => $user->id,
                'order_id' => rand(1, 1000), // Mock order ID
                'cryptocurrency_symbol' => $crypto->symbol,
                'quantity' => $quantity,
                'price' => $price,
                'total_amount' => $quantity * $price,
                'side' => $side,
                'fee' => ($quantity * $price) * 0.001, // 0.1% fee
                'status' => 'completed',
                'executed_at' => now()->subDays(rand(0, 30)),
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }

        echo "✅ Created 100 sample trades\n";
    }

    private function createSampleOrders($users, $cryptos): void
    {
        $orderTypes = ['market', 'limit'];
        $orderSides = ['buy', 'sell'];
        $orderStatuses = ['pending', 'filled', 'cancelled', 'partially_filled'];
        
        for ($i = 0; $i < 50; $i++) {
            $user = $users->random();
            $crypto = $cryptos->random();
            $type = $orderTypes[array_rand($orderTypes)];
            $side = $orderSides[array_rand($orderSides)];
            $status = $orderStatuses[array_rand($orderStatuses)];
            $quantity = rand(1, 1000) / 100;
            $price = $crypto->current_price * (rand(90, 110) / 100);
            
            Order::create([
                'user_id' => $user->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'type' => $type,
                'side' => $side,
                'quantity' => $quantity,
                'price' => $price,
                'status' => $status,
                'filled_quantity' => $status === 'filled' ? $quantity : ($status === 'partially_filled' ? $quantity * 0.5 : 0),
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }

        echo "✅ Created 50 sample orders\n";
    }

    private function createSampleTransactions($users, $cryptos): void
    {
        $transactionTypes = ['deposit', 'withdrawal', 'fee', 'admin_adjustment'];
        $statuses = ['pending', 'completed', 'failed'];
        
        for ($i = 0; $i < 200; $i++) {
            $user = $users->random();
            $crypto = $cryptos->random();
            $type = $transactionTypes[array_rand($transactionTypes)];
            $status = $statuses[array_rand($statuses)];
            $amount = rand(1, 10000) / 100;
            
            TransactionRecord::create([
                'user_id' => $user->id,
                'type' => $type,
                'cryptocurrency_symbol' => $crypto->symbol,
                'amount' => $amount,
                'fee' => $type === 'withdrawal' ? $amount * 0.005 : 0, // 0.5% withdrawal fee
                'status' => $status,
                'description' => $this->getTransactionDescription($type),
                'reference_id' => Str::uuid(),
                'processed_at' => $status === 'completed' ? now()->subDays(rand(0, 30)) : null,
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }

        echo "✅ Created 200 sample transaction records\n";
    }

    private function createSampleKycDocuments($users): void
    {
        $documentTypes = ['passport', 'national_id', 'driving_license'];
        $statuses = ['pending', 'approved', 'rejected'];
        
        foreach ($users->take(10) as $user) {
            $documentType = $documentTypes[array_rand($documentTypes)];
            $status = $statuses[array_rand($statuses)];
            
            KycDocument::create([
                'user_id' => $user->id,
                'document_type' => $documentType,
                'document_number' => 'DOC' . rand(100000, 999999),
                'document_front_path' => 'kyc/documents/sample_front.jpg',
                'document_back_path' => $documentType !== 'passport' ? 'kyc/documents/sample_back.jpg' : null,
                'selfie_path' => 'kyc/selfies/sample_selfie.jpg',
                'status' => $status,
                'rejection_reason' => $status === 'rejected' ? 'Document quality is poor' : null,
                'verified_by' => $status !== 'pending' ? User::where('is_admin', true)->first()->id : null,
                'verified_at' => $status !== 'pending' ? now()->subDays(rand(1, 15)) : null,
                'expires_at' => $status === 'approved' ? now()->addYears(2) : null,
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }

        echo "✅ Created 10 sample KYC documents\n";
    }

    private function createSampleSupportTickets($users, $admin): void
    {
        $categories = ['technical', 'account', 'trading', 'deposit', 'withdrawal', 'kyc', 'other'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        
        $subjects = [
            'Unable to login to my account',
            'Withdrawal not processed',
            'Trading fee calculation issue',
            'KYC document rejected',
            'Deposit not showing in wallet',
            'Two-factor authentication problem',
            'Order execution delay',
            'Account verification needed',
            'Password reset not working',
            'Balance discrepancy'
        ];

        for ($i = 0; $i < 25; $i++) {
            $user = $users->random();
            $category = $categories[array_rand($categories)];
            $priority = $priorities[array_rand($priorities)];
            $status = $statuses[array_rand($statuses)];
            $subject = $subjects[array_rand($subjects)];
            
            $ticket = SupportTicket::create([
                'user_id' => $user->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $status !== 'open' ? $admin->id : null,
                'resolved_at' => $status === 'resolved' ? now()->subDays(rand(1, 10)) : null,
                'satisfaction_rating' => $status === 'resolved' ? rand(3, 5) : null,
                'created_at' => now()->subDays(rand(1, 30)),
            ]);

            // Create initial message
            TicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'message' => $this->getTicketMessage($category),
                'is_internal' => false,
                'created_at' => $ticket->created_at,
            ]);

            // Create admin response if ticket is not open
            if ($status !== 'open') {
                TicketMessage::create([
                    'support_ticket_id' => $ticket->id,
                    'user_id' => $admin->id,
                    'message' => 'Thank you for contacting us. We are looking into your issue.',
                    'is_internal' => false,
                    'created_at' => $ticket->created_at->addHours(2),
                ]);
            }
        }

        echo "✅ Created 25 sample support tickets with messages\n";
    }

    private function createSampleReferralPrograms($users): void
    {
        foreach ($users->take(8) as $user) {
            $referralCode = strtoupper(Str::random(8));
            $totalReferrals = rand(0, 50);
            $activeReferrals = rand(0, $totalReferrals);
            $totalEarned = $totalReferrals * rand(10, 100);
            
            ReferralProgram::create([
                'user_id' => $user->id,
                'referral_code' => $referralCode,
                'commission_rate' => rand(3, 10) / 100, // 3-10%
                'total_referrals' => $totalReferrals,
                'active_referrals' => $activeReferrals,
                'total_earned' => $totalEarned,
                'pending_payout' => rand(0, $totalEarned / 2),
                'status' => 'active',
                'created_at' => now()->subDays(rand(30, 365)),
            ]);
        }

        echo "✅ Created 8 sample referral programs\n";
    }

    private function createSampleInvestments($users, $cryptos): void
    {
        $investmentTypes = ['staking', 'savings', 'liquidity_mining', 'yield_farming'];
        $statuses = ['active', 'completed', 'cancelled', 'matured'];
        
        for ($i = 0; $i < 30; $i++) {
            $user = $users->random();
            $crypto = $cryptos->random();
            $type = $investmentTypes[array_rand($investmentTypes)];
            $status = $statuses[array_rand($statuses)];
            $amount = rand(100, 10000);
            $durationDays = rand(30, 365);
            $returnRate = rand(5, 25); // 5-25% annual return
            $startedAt = now()->subDays(rand(1, $durationDays));
            $currentValue = $amount * (1 + ($returnRate / 100) * ($startedAt->diffInDays(now()) / 365));
            
            Investment::create([
                'user_id' => $user->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'investment_type' => $type,
                'amount' => $amount,
                'duration_days' => $durationDays,
                'expected_return_rate' => $returnRate,
                'current_value' => $status === 'active' ? $currentValue : $amount,
                'status' => $status,
                'started_at' => $startedAt,
                'maturity_date' => $startedAt->copy()->addDays($durationDays),
                'completed_at' => $status !== 'active' ? now()->subDays(rand(1, 30)) : null,
                'created_at' => $startedAt,
            ]);
        }

        echo "✅ Created 30 sample investments\n";
    }

    private function getTransactionDescription($type): string
    {
        $descriptions = [
            'deposit' => 'Cryptocurrency deposit',
            'withdrawal' => 'Cryptocurrency withdrawal',
            'fee' => 'Trading fee',
            'admin_adjustment' => 'Admin balance adjustment',
        ];

        return $descriptions[$type] ?? 'Transaction';
    }

    private function getTicketMessage($category): string
    {
        $messages = [
            'technical' => 'I am experiencing technical difficulties with the platform. The page keeps loading and I cannot access my account.',
            'account' => 'I need help with my account settings. I cannot update my profile information.',
            'trading' => 'My trading order was not executed properly. Can you please check what happened?',
            'deposit' => 'I made a deposit 2 hours ago but it is not showing in my wallet. Transaction ID: TXN123456',
            'withdrawal' => 'My withdrawal request has been pending for 24 hours. When will it be processed?',
            'kyc' => 'My KYC documents were rejected but I do not understand why. Can you please review them again?',
            'other' => 'I have a general question about the platform features and how to use them effectively.',
        ];

        return $messages[$category] ?? 'I need assistance with my account.';
    }
}