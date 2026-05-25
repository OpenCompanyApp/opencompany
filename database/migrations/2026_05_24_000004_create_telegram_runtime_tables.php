<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_integration_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->string('bot_id')->nullable();
            $table->string('bot_username')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret_fingerprint')->nullable();
            $table->json('allowed_updates')->nullable();
            $table->string('command_sync_status')->default('unknown');
            $table->string('profile_sync_status')->default('unknown');
            $table->unsignedInteger('pending_update_count')->nullable();
            $table->string('default_mode')->default('command_center');
            $table->string('default_agent_id')->nullable();
            $table->json('notification_policy')->nullable();
            $table->string('health_status')->default('unknown');
            $table->text('last_health_error')->nullable();
            $table->timestamp('last_health_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->foreign('default_agent_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['workspace_id', 'integration_setting_id'], 'telegram_profiles_workspace_setting_unique');
        });

        Schema::create('telegram_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->string('chat_id');
            $table->string('chat_type')->default('private');
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->string('topic_id')->nullable();
            $table->string('direct_messages_topic_id')->nullable();
            $table->string('channel_id')->nullable();
            $table->string('default_agent_id')->nullable();
            $table->string('mode')->default('command_center');
            $table->boolean('observed_context_enabled')->default(false);
            $table->json('notification_policy')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->nullOnDelete();
            $table->foreign('default_agent_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['integration_setting_id', 'chat_id', 'topic_id', 'direct_messages_topic_id'], 'telegram_conversations_external_unique');
        });

        Schema::create('telegram_update_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->string('update_id');
            $table->string('update_type')->default('unknown');
            $table->string('payload_hash', 64);
            $table->json('payload');
            $table->string('status')->default('received');
            $table->uuid('telegram_conversation_id')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->foreign('telegram_conversation_id')->references('id')->on('telegram_conversations')->nullOnDelete();
            $table->unique(['integration_setting_id', 'update_id'], 'telegram_update_receipts_setting_update_unique');
            $table->index(['status', 'received_at']);
        });

        Schema::create('telegram_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->nullableUuidMorphs('source');
            $table->string('chat_id');
            $table->string('topic_id')->nullable();
            $table->string('direct_messages_topic_id')->nullable();
            $table->string('telegram_message_id')->nullable();
            $table->string('media_group_id')->nullable();
            $table->string('parse_mode')->nullable();
            $table->string('renderer_version')->nullable();
            $table->string('status')->default('pending');
            $table->uuid('previous_delivery_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('provider_error_code')->nullable();
            $table->text('provider_error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->index(['chat_id', 'topic_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('telegram_deliveries', function (Blueprint $table) {
            $table->foreign('previous_delivery_id')->references('id')->on('telegram_deliveries')->nullOnDelete();
        });

        Schema::create('telegram_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->string('token', 64)->unique();
            $table->string('interaction_type');
            $table->nullableUuidMorphs('target');
            $table->json('payload')->nullable();
            $table->json('allowed_actor_rule')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by_id')->nullable();
            $table->string('final_status')->nullable();
            $table->string('payload_checksum', 64)->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->foreign('resolved_by_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['interaction_type', 'expires_at']);
        });

        Schema::create('telegram_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->string('scope_type');
            $table->string('scope_id');
            $table->string('event_type');
            $table->string('severity')->default('normal');
            $table->json('filters')->nullable();
            $table->string('schedule')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->unique(['integration_setting_id', 'scope_type', 'scope_id', 'event_type', 'severity'], 'telegram_subscriptions_scope_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_subscriptions');
        Schema::dropIfExists('telegram_interactions');
        Schema::dropIfExists('telegram_deliveries');
        Schema::dropIfExists('telegram_update_receipts');
        Schema::dropIfExists('telegram_conversations');
        Schema::dropIfExists('telegram_integration_profiles');
    }
};
