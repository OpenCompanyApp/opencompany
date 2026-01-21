<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stats', function (Blueprint $table) {
            $table->string('id')->primary()->default('main');
            $table->decimal('credits_used', 12, 2)->default(0);
            $table->decimal('credits_remaining', 12, 2)->default(10000);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats');
    }
};
