<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('integration_id')->unique(); // e.g., 'glm', 'openai', 'anthropic'
            $table->text('config'); // Encrypted via model cast: api_key, url, default_model, etc.
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
