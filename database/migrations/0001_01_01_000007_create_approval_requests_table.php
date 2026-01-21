<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->enum('type', ['budget', 'action', 'spawn', 'access']);
            $table->string('title');
            $table->text('description');
            $table->string('requester_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('responded_by_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('responded_by_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
