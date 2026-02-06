<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id');
            $table->text('personality')->nullable();    // SOUL.md - behavior guidelines
            $table->text('instructions')->nullable(); // BOOTSTRAP.md - operating manual
            $table->json('identity')->nullable();     // IDENTITY.md - structured {name, emoji, type, ...}
            $table->text('tool_notes')->nullable();   // TOOLS.md - tool usage notes
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_configurations');
    }
};
