<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ChannelSeeder::class,
            MessageSeeder::class,
            TaskSeeder::class,
            DocumentSeeder::class,
            ApprovalSeeder::class,
            ActivitySeeder::class,
            NotificationSeeder::class,
            CreditSeeder::class,
            DirectMessageSeeder::class,
        ]);
    }
}
