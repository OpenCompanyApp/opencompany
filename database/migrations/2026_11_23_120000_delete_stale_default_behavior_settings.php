<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Delete the stored `agents.default_behavior` app setting in every workspace.
 *
 * Agent behaviour modes (autonomous, supervised, strict) are deleted in favour
 * of the policy engine and its Personal, Team and Company presets (Product
 * Direction pillar 5). The setting is gone from `AppSetting::defaults()` and
 * the Settings UI, so stored rows would only resurface through
 * `AppSetting::getByCategory('agents')`. Nothing reads them. Do not
 * reintroduce a behaviour-mode setting: autonomy is expressed as scoped grants.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('app_settings')
            ->where('category', 'agents')
            ->where('key', 'default_behavior')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally not restored: behaviour modes no longer exist.
    }
};
