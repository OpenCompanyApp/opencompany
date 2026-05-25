<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_update_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_update_receipts', 'duplicate_count')) {
                $table->unsignedInteger('duplicate_count')->default(0)->after('retry_count');
            }

            if (! Schema::hasColumn('telegram_update_receipts', 'last_duplicate_at')) {
                $table->timestamp('last_duplicate_at')->nullable()->after('duplicate_count');
            }

            if (! Schema::hasColumn('telegram_update_receipts', 'diagnostics')) {
                $table->json('diagnostics')->nullable()->after('payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('telegram_update_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_update_receipts', 'diagnostics')) {
                $table->dropColumn('diagnostics');
            }

            if (Schema::hasColumn('telegram_update_receipts', 'last_duplicate_at')) {
                $table->dropColumn('last_duplicate_at');
            }

            if (Schema::hasColumn('telegram_update_receipts', 'duplicate_count')) {
                $table->dropColumn('duplicate_count');
            }
        });
    }
};
