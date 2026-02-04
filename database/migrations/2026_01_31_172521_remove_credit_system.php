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
        // Drop the credit_transactions table
        Schema::dropIfExists('credit_transactions');

        // Remove credit columns from stats table
        Schema::table('stats', function (Blueprint $table) {
            if (Schema::hasColumn('stats', 'credits_used')) {
                $table->dropColumn('credits_used');
            }
            if (Schema::hasColumn('stats', 'credits_remaining')) {
                $table->dropColumn('credits_remaining');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate credit columns in stats table
        Schema::table('stats', function (Blueprint $table) {
            $table->decimal('credits_used', 10, 2)->default(0);
            $table->decimal('credits_remaining', 10, 2)->default(10000);
        });

        // Recreate credit_transactions table
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('description');
            $table->uuid('user_id')->nullable();
            $table->uuid('agent_id')->nullable();
            $table->timestamps();
        });
    }
};
