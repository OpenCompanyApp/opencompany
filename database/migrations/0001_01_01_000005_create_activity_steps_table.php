<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_steps', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->text('description');
            $table->enum('status', ['completed', 'in_progress', 'pending']);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_steps');
    }
};
