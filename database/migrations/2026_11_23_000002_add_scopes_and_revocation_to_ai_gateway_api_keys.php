<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scoped, owned, revocable, always-expiring AI Gateway keys (GIN-1203;
 * ROADMAP Phase 0 P0 item 7).
 *
 * - `scopes`: {"actions": [...], "models": [...]}; models may be ["*"].
 * - `created_by_user_id`: the human who created the key (owner binding).
 * - `revoked_at`: revocation keeps the row for the audit trail; revoked keys
 *   never authenticate.
 *
 * Backfill: keys created before this migration had no scopes and possibly
 * no expiry. They keep working with explicit full scopes (every operation,
 * every model the workspace exposes) and get a 90-day expiry, so no key is
 * open-ended any more. Keys with an expiry keep it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_gateway_api_keys', function (Blueprint $table) {
            $table->json('scopes')->nullable();
            $table->string('created_by_user_id')->nullable();
            $table->dateTime('revoked_at')->nullable();
        });

        DB::table('ai_gateway_api_keys')->whereNull('scopes')->update([
            'scopes' => json_encode(['actions' => ['models.list', 'chat.completions', 'embeddings'], 'models' => ['*']]),
        ]);
        DB::table('ai_gateway_api_keys')->whereNull('expires_at')->update([
            'expires_at' => now()->addDays(90),
        ]);
    }

    public function down(): void
    {
        Schema::table('ai_gateway_api_keys', function (Blueprint $table) {
            $table->dropColumn(['scopes', 'created_by_user_id', 'revoked_at']);
        });
    }
};
