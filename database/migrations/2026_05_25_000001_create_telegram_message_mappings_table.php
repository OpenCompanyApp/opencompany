<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_message_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->index();
            $table->string('integration_setting_id');
            $table->uuid('telegram_conversation_id');
            $table->string('channel_id');
            $table->string('message_id');
            $table->string('chat_id');
            $table->string('topic_id')->nullable();
            $table->string('direct_messages_topic_id')->nullable();
            $table->string('telegram_message_id');
            $table->string('mapping_type')->default('primary');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('integration_setting_id')->references('id')->on('integration_settings')->cascadeOnDelete();
            $table->foreign('telegram_conversation_id')->references('id')->on('telegram_conversations')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('channels')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->unique(['integration_setting_id', 'chat_id', 'topic_id', 'direct_messages_topic_id', 'telegram_message_id'], 'telegram_message_mappings_external_unique');
            $table->index(['message_id', 'mapping_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_message_mappings');
    }
};
