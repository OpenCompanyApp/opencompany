<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_permissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('agent_id');
            $table->string('scope_type'); // 'tool', 'channel', 'folder'
            $table->string('scope_key'); // tool slug, channel UUID, or folder UUID
            $table->string('permission')->default('allow'); // 'allow' or 'deny'
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['agent_id', 'scope_type', 'scope_key']);
            $table->index(['agent_id', 'scope_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_permissions');
    }
};
