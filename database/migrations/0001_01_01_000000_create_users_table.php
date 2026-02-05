<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->enum('type', ['human', 'agent']);
            $table->string('agent_type')->nullable();
            $table->enum('status', ['idle', 'working', 'offline'])->default('offline');
            $table->enum('presence', ['online', 'away', 'busy', 'offline'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('current_task')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_temporary')->default(false);
            $table->string('manager_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Add self-referential foreign key after table creation
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
