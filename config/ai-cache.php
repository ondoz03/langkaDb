<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Cache TTL Configuration
    |--------------------------------------------------------------------------
    |
    | Define the Time-To-Live (TTL) in seconds for each cache type.
    | These can be overridden via environment variables.
    |
    */

    'ttl' => [
        'recommendation' => (int) env('AI_CACHE_TTL_RECOMMENDATION', 1800), // 30 minutes
        'chat'           => (int) env('AI_CACHE_TTL_CHAT', 3600),          // 1 hour
        'health'         => (int) env('AI_CACHE_TTL_HEALTH', 3600),        // 1 hour
        'schema'         => (int) env('AI_CACHE_TTL_SCHEMA', 86400),       // 24 hours
        'documentation'  => (int) env('AI_CACHE_TTL_DOCS', 604800),        // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix used for all AI cache keys to avoid collisions.
    |
    */

    'prefix' => env('AI_CACHE_PREFIX', 'ai'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store to use when the primary store (e.g. Redis) is
    | unavailable. Defaults to 'database' which uses the database
    | cache table.
    |
    */

    'fallback_store' => env('AI_CACHE_FALLBACK_STORE', 'database'),

];
