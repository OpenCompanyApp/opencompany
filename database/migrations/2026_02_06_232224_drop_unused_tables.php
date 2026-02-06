<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agent_settings');
        Schema::dropIfExists('agent_configurations');
        Schema::dropIfExists('capabilities');
        Schema::dropIfExists('stats');
    }

    public function down(): void
    {
        // Tables were empty/unused — no restoration needed
    }
};
