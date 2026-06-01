<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AICacheService
{
    private const int DEFAULT_TTL = 1800;

    private ?array $ttlConfig = null;

    private ?CacheRepository $store = null;

    private ?string $storeName = null;

    public function __construct()
    {
        // Config is loaded lazily via ttlConfig() to avoid container
        // auto-resolution issues during testing.
    }

    // -----------------------------------------------------------------------
    //  Public API
    // -----------------------------------------------------------------------

    /**
     * Retrieve a value from cache.
     */
    public function get(string $key): mixed
    {
        return $this->resolveStore()->get($key);
    }

    /**
     * Store a value in cache with a given TTL.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->resolveStore()->put($key, $value, $ttl ?? self::DEFAULT_TTL);
    }

    /**
     * Return the cached value if it exists, otherwise execute the callback
     * and store the result (cache-aside / cache-through pattern).
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed
    {
        return $this->resolveStore()->remember($key, $ttl ?? self::DEFAULT_TTL, $callback);
    }

    /**
     * Remove one or more keys from cache.
     */
    public function invalidate(string|array $keys): void
    {
        $keys = is_string($keys) ? [$keys] : $keys;

        $store = $this->resolveStore();

        foreach ($keys as $key) {
            $store->forget($key);
        }
    }

    /**
     * Invalidate all cached entries belonging to a specific connection.
     *
     * Removes the schema snapshot key; the recommendation and health keys
     * will naturally miss on their next lookup because the schema hash will
     * have changed.
     */
    public function invalidateConnection(string $connectionId): void
    {
        $prefix = config('ai-cache.prefix', 'ai');
        $this->invalidate("{$prefix}:schema:{$connectionId}");
    }

    /**
     * Attempt to flush every AI-prefixed key from the cache.
     *
     * With the Redis store this scans and deletes by pattern. On other
     * stores (database, file, array) a full flush is not supported because
     * we cannot safely delete only the AI prefix — a warning is logged
     * instead.
     */
    public function flush(): void
    {
        $prefix = config('ai-cache.prefix', 'ai');
        $store = $this->resolveStore();

        // Laravel's Redis store exposes a ->connection() method that returns
        // the underlying PhpRedis / predis connection.
        if (method_exists($store, 'connection')) {
            try {
                $redis = $store->connection();
                $pattern = "{$prefix}:*";

                // Use SCAN for production-safety (avoids blocking KEYS).
                $allKeys = [];
                $cursor = 0;

                do {
                    $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
                    $cursor = (int) $result[0];
                    $keys = $result[1] ?? [];

                    if (!empty($keys)) {
                        $allKeys = array_merge($allKeys, $keys);
                    }
                } while ($cursor > 0);

                if (!empty($allKeys)) {
                    $redis->del($allKeys);
                }

                Log::info('AICacheService: flushed all AI-cache keys via Redis SCAN', [
                    'keys_removed' => count($allKeys),
                ]);

                return;
            } catch (\Throwable $e) {
                Log::warning('AICacheService: Redis flush failed', [
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        Log::warning('AICacheService: full flush requested but store does not support pattern-based deletion', [
            'store' => $this->storeName,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Cache-key helpers
    // -----------------------------------------------------------------------

    /**
     * Build a cache key for the given type and identifiers.
     *
     * @throws \InvalidArgumentException when $type is unknown.
     */
    public function key(
        string $type,
        string $connectionId,
        ?string $hash = null,
        ?string $sessionId = null,
    ): string {
        $prefix = config('ai-cache.prefix', 'ai');

        return match ($type) {
            'recommendation' => "{$prefix}:recommendation:{$connectionId}:{$hash}",
            'chat'           => "{$prefix}:chat:{$connectionId}:{$sessionId}:{$hash}",
            'health'         => "{$prefix}:health:{$connectionId}:{$hash}",
            'schema'         => "{$prefix}:schema:{$connectionId}",
            default          => throw new \InvalidArgumentException("Unknown cache type: {$type}"),
        };
    }

    /**
     * Produce a deterministic hash from a schema array (md5 of its JSON).
     */
    public function schemaHash(array $schema): string
    {
        return md5(json_encode($schema, JSON_THROW_ON_ERROR));
    }

    /**
     * Produce a deterministic hash from a message string.
     */
    public function messageHash(string $message): string
    {
        return md5(trim($message));
    }

    // -----------------------------------------------------------------------
    //  TTL
    // -----------------------------------------------------------------------

    /**
     * Return the configured TTL (in seconds) for a given cache type.
     */
    public function ttl(string $type): int
    {
        return $this->ttlConfig()[$type] ?? self::DEFAULT_TTL;
    }

    /**
     * Lazily load TTL configuration from the ai-cache config file.
     */
    private function ttlConfig(): array
    {
        if ($this->ttlConfig === null) {
            $this->ttlConfig = config('ai-cache.ttl', [
                'recommendation' => 1800,
                'chat'           => 3600,
                'health'         => 900,
                'schema'         => 300,
            ]);
        }

        return $this->ttlConfig;
    }

    // -----------------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve the cache store, falling back to the secondary store (e.g.
     * database) when the primary store is unavailable.
     */
    private function resolveStore(): CacheRepository
    {
        if ($this->store !== null) {
            return $this->store;
        }

        // Use array store in testing environment to avoid DB dependency.
        if (app()->environment('testing')) {
            $this->store = Cache::store('array');
            $this->storeName = 'array';
            return $this->store;
        }

        $primary = config('cache.default', 'database');

        try {
            $store = Cache::store($primary);

            // Keep the resolved store in memory for the remainder of this
            // request so we don't hammer the connection on every call.
            $this->store = $store;
            $this->storeName = $primary;

            return $store;
        } catch (\Throwable $e) {
            Log::warning('AICacheService: primary store unavailable, falling back', [
                'primary' => $primary,
                'error'   => $e->getMessage(),
            ]);
        }

        $fallback = config('ai-cache.fallback_store', 'database');

        try {
            $store = Cache::store($fallback);

            $this->store = $store;
            $this->storeName = $fallback;

            Log::info('AICacheService: using fallback store', [
                'fallback' => $fallback,
            ]);

            return $store;
        } catch (\Throwable $e) {
            // Absolute last resort — the array store never throws.
            Log::error('AICacheService: fallback store also unavailable, using array', [
                'fallback' => $fallback,
                'error'    => $e->getMessage(),
            ]);

            $this->store = Cache::store('array');
            $this->storeName = 'array';

            return $this->store;
        }
    }
}
