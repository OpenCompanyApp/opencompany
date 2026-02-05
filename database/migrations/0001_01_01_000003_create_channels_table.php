<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('type')->default('public');
            $table->text('description')->nullable();
            $table->string('creator_id')->nullable();
            $table->boolean('is_temporary')->default(false);
            $table->timestamps();

            $table->foreign('creator_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
