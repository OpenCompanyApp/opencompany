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

        Schema::create('document_chunks', function (Blueprint $table) use ($isPgsql) {
            $table->uuid('id')->primary();
            $table->string('document_id');
            $table->text('content');
            $table->string('content_hash', 64);

            if ($isPgsql) {
                $table->vector('embedding', 1536);
                $table->jsonb('metadata')->nullable();
            } else {
                $table->text('embedding')->nullable();
                $table->json('metadata')->nullable();
            }

            $table->string('collection')->default('general');
            $table->string('agent_id')->nullable();
            $table->integer('chunk_index')->default(0);
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['collection', 'agent_id']);
            $table->index('content_hash');
            $table->index('document_id');
        });

        if ($isPgsql) {
            DB::statement('CREATE INDEX document_chunks_embedding_idx ON document_chunks USING hnsw (embedding vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
