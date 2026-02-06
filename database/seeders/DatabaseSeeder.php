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
            IntegrationSettingSeeder::class,
            UserSeeder::class,
            ChannelSeeder::class,
            MessageSeeder::class,
            ListItemSeeder::class,
            AgentTaskSeeder::class,
            DocumentSeeder::class,
            ApprovalSeeder::class,
            ActivitySeeder::class,
            NotificationSeeder::class,
            DirectMessageSeeder::class,
            AgentPermissionSeeder::class,
            DataTableSeeder::class,
            CalendarEventSeeder::class,
        ]);
    }
}
