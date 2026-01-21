<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->enum('type', ['usage', 'purchase', 'refund', 'bonus']);
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->string('user_id')->nullable();
            $table->string('task_id')->nullable();
            $table->string('approval_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('set null');
            $table->foreign('approval_id')->references('id')->on('approval_requests')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
