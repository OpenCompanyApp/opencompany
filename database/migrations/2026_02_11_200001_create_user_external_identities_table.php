<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_external_identities', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id');
            $table->string('provider');
            $table->string('external_id');
            $table->string('display_name')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['provider', 'external_id']);
        });

        // Mark existing Telegram shadow users as ephemeral
        User::where('email', 'LIKE', 'telegram-%@external.opencompany')
            ->update(['is_ephemeral' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_external_identities');
    }
};
