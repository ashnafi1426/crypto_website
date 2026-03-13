<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Cryptocurrency;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@cryptoexchange.com',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
            'is_admin' => true,
            'status' => 'active',
            'kyc_status' => 'approved',
            'kyc_approved_at' => now(),
        ]);

        echo "✅ Admin user created: admin@cryptoexchange.com / admin123\n";

        // Create some test regular users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'kyc_status' => 'approved',
                'kyc_approved_at' => now(),
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'kyc_status' => 'pending',
            ],
            [
                'name' => 'Bob Wilson',
                'email' => 'bob@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'kyc_status' => 'rejected',
            ],
            [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'password' => Hash::make('password123'),
                'status' => 'suspended',
                'kyc_status' => 'approved',
                'kyc_approved_at' => now()->subDays(30),
            ],
            [
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'kyc_status' => 'not_submitted',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create(array_merge($userData, [
                'email_verified_at' => now(),
            ]));

            // Create wallets for each user with some cryptocurrencies
            $cryptos = Cryptocurrency::take(5)->get();
            foreach ($cryptos as $crypto) {
                Wallet::create([
                    'user_id' => $user->id,
                    'cryptocurrency_symbol' => $crypto->symbol,
                    'balance' => rand(0, 10000) / 100, // Random balance between 0-100
                    'reserved_balance' => 0,
                ]);
            }
        }

        echo "✅ Created 5 test users with wallets\n";

        // Create wallets for admin user
        $cryptos = Cryptocurrency::all();
        foreach ($cryptos as $crypto) {
            Wallet::create([
                'user_id' => $admin->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => 1000000, // Admin has large balances for testing
                'reserved_balance' => 0,
            ]);
        }

        echo "✅ Created admin wallets with large balances\n";
    }
}