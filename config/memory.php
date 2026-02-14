<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding Configuration
    |--------------------------------------------------------------------------
    */

    'embedding' => [
        'provider' => env('MEMORY_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('MEMORY_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('MEMORY_EMBEDDING_DIMENSIONS', 1536),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking Configuration
    |--------------------------------------------------------------------------
    */

    'chunking' => [
        'max_chunk_size' => (int) env('MEMORY_CHUNK_SIZE', 512),
        'chunk_overlap' => (int) env('MEMORY_CHUNK_OVERLAP', 64),
        'separator' => "\n\n",
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection Defaults
    |--------------------------------------------------------------------------
    */

    'collections' => [
        'default' => 'general',
        'per_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    */

    'search' => [
        'default_limit' => 10,
        'max_limit' => 50,
        'hybrid_weights' => [
            'semantic' => 0.7,
            'keyword' => 0.3,
        ],
        'min_similarity' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reranking Configuration
    |--------------------------------------------------------------------------
    |
    | Cross-encoder reranking re-scores search results for better relevance.
    | Enabled by default with a local Ollama model (no API key needed).
    |
    */

    'reranking' => [
        'enabled' => env('MEMORY_RERANKING_ENABLED', true),
        'provider' => env('MEMORY_RERANKING_PROVIDER', 'ollama'),
        'model' => env('MEMORY_RERANKING_MODEL', 'dengcao/Qwen3-Reranker-0.6B:Q8_0'),
        'top_k' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Context Windows
    |--------------------------------------------------------------------------
    |
    | Maps model name prefixes to their context window sizes (in tokens).
    | Used by ModelContextRegistry to determine compaction thresholds per model.
    | Lookup order: user overrides (AppSetting) → exact match → longest prefix
    | match → Levenshtein fuzzy match → default.
    |
    */

    'context_windows' => [
        'default' => (int) env('MEMORY_DEFAULT_CONTEXT_WINDOW', 32_000),
        'hard_minimum' => 16_000,
        'models' => [
            // Anthropic
            'claude-opus-4'     => 200_000,
            'claude-sonnet-4'   => 200_000,
            'claude-haiku-3'    => 200_000,
            'claude-3'          => 200_000,
            // OpenAI
            'gpt-5'            => 128_000,
            'gpt-4o-mini'      => 128_000,
            'gpt-4o'           => 128_000,
            'gpt-4-turbo'      => 128_000,
            'gpt-4'            => 8_192,
            'o1'               => 200_000,
            'o3'               => 200_000,
            'o4-mini'          => 200_000,
            // Codex (ChatGPT subscription)
            'gpt-5.3-codex'   => 128_000,
            'gpt-5.2-codex'   => 128_000,
            'gpt-5.1-codex'   => 128_000,
            'gpt-5-codex'     => 128_000,
            // Gemini
            'gemini-2.0'      => 1_048_576,
            'gemini-1.5-pro'  => 2_097_152,
            'gemini-1.5'      => 1_048_576,
            'gemini-3'        => 1_048_576,
            // Groq-hosted
            'llama-3.3'       => 128_000,
            'llama-3.1'       => 128_000,
            'llama-3-'        => 8_192,
            'mixtral'         => 32_768,
            // xAI
            'grok-3'          => 128_000,
            'grok-2'          => 128_000,
            // DeepSeek
            'deepseek-v3'     => 128_000,
            'deepseek'        => 64_000,
            // Mistral
            'mistral-large'   => 128_000,
            'mistral-nemo'    => 128_000,
            'mistral'         => 32_768,
            'codestral'       => 32_768,
            // GLM (Zhipu AI)
            'glm-5'           => 128_000,
            'glm-4'           => 128_000,
            // Ollama common
            'qwen2.5'         => 128_000,
            'qwen2'           => 32_768,
            'phi-4'           => 16_384,
            'phi-3'           => 128_000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Memory Scope
    |--------------------------------------------------------------------------
    |
    | Controls where memory tools (save_memory, recall_memory) can be used.
    | Options: 'dm_only' (private channels only), 'all', 'none'
    |
    */

    'scope' => [
        'default' => env('MEMORY_SCOPE_DEFAULT', 'dm_only'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Compaction
    |--------------------------------------------------------------------------
    |
    | Controls when and how conversation history is compacted (summarized)
    | to stay within the model's context window.
    |
    */

    'compaction' => [
        'enabled' => env('MEMORY_COMPACTION_ENABLED', true),
        'threshold_ratio' => 0.75,
        'keep_recent_tokens' => (int) env('MEMORY_KEEP_RECENT_TOKENS', 20_000),
        'min_keep_messages' => 3,
        'safety_margin' => 1.2,
        'output_reserve' => 4_096,
        'system_prompt_fallback_reserve' => 10_000,
        'summary_model' => env('MEMORY_SUMMARY_MODEL', 'anthropic:claude-sonnet-4-5-20250929'),
        'summary_max_tokens' => 2_000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation Loading
    |--------------------------------------------------------------------------
    |
    | Message loading is compaction-driven: all messages after the last
    | compaction point are loaded. Compaction manages the token budget
    | automatically — no artificial message count limits.
    |
    */

    'conversation' => [],

    /*
    |--------------------------------------------------------------------------
    | Memory Flush (STM → LTM Promotion)
    |--------------------------------------------------------------------------
    |
    | Before compaction summarizes older messages, a silent agent turn can
    | promote important information to long-term memory via save_memory.
    | The "soft zone" is the token range just below the compaction threshold
    | where a flush is triggered.
    |
    */

    'memory_flush' => [
        'enabled' => env('MEMORY_FLUSH_ENABLED', true),
        'soft_threshold_tokens' => 4_000,
        'max_flushes_per_cycle' => 1,
    ],

];
