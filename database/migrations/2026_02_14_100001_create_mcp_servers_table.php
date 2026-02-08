<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('url');
            $table->string('auth_type')->default('none');
            $table->text('auth_config')->nullable();
            $table->json('headers')->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('discovered_tools')->nullable();
            $table->json('server_info')->nullable();
            $table->timestamp('tools_discovered_at')->nullable();
            $table->integer('timeout')->default(30);
            $table->string('icon')->default('ph:plug');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_servers');
    }
};
