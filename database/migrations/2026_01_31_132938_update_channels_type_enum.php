<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check constraints only apply to PostgreSQL; SQLite has no ALTER TABLE ... DROP CONSTRAINT
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
            DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('public', 'private', 'agent', 'dm', 'external'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE channels DROP CONSTRAINT IF EXISTS channels_type_check");
            DB::statement("ALTER TABLE channels ADD CONSTRAINT channels_type_check CHECK (type IN ('public', 'private', 'agent'))");
        }
    }
};
