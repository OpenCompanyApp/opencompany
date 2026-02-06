<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
            DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('public', 'private', 'dm', 'external'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
            DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('public', 'private', 'agent', 'dm', 'external'))");
        }
    }
};
