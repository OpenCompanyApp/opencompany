<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::getDriverName() === 'pgsql';

        Schema::create('embedding_cache', function (Blueprint $table) use ($isPgsql) {
            $table->string('id', 64)->primary();
            $table->string('provider', 50);
            $table->string('model', 100);

            if ($isPgsql) {
                $table->vector('embedding');
            } else {
                $table->text('embedding')->nullable();
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embedding_cache');
    }
};
