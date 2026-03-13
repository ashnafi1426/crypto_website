<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CryptocurrencySeeder::class,
            AdminUserSeeder::class,
            AdminTestDataSeeder::class,
        ]);

        echo "\n🎉 Database seeding completed successfully!\n";
        echo "📧 Admin Login: admin@cryptoexchange.com\n";
        echo "🔑 Admin Password: admin123\n";
        echo "🌐 You can now test the admin backend functionality!\n\n";
    }
}
