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

];
