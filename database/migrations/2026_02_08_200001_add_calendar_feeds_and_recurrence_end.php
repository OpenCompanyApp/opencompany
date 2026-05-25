<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // recurrence_end caps recurring events without changing the recurrence
        // rule format already stored on calendar_events.
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dateTime('recurrence_end')->nullable()->after('recurrence_rule');
        });

        // Calendar feed tokens allow read-only external calendar subscriptions
        // without exposing the user's normal session credentials.
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_feeds');

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('recurrence_end');
        });
    }
};
