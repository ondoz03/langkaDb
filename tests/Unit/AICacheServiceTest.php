<?php

declare(strict_types=1);

use App\Modules\AIAgent\Services\AICacheService;
use Illuminate\Support\Facades\Cache;

// -----------------------------------------------------------------------
//  Cache key generation
// -----------------------------------------------------------------------

it('generates a recommendation cache key', function () {
    $service = app(AICacheService::class);

    $key = $service->key('recommendation', 'conn_1', 'abc123');

    expect($key)->toBe('ai:recommendation:conn_1:abc123');
});

it('generates a chat cache key', function () {
    $service = app(AICacheService::class);

    $key = $service->key('chat', 'conn_1', 'msg_hash', 'sess_42');

    expect($key)->toBe('ai:chat:conn_1:sess_42:msg_hash');
});

it('generates a health cache key', function () {
    $service = app(AICacheService::class);

    $key = $service->key('health', 'conn_1', 'abc123');

    expect($key)->toBe('ai:health:conn_1:abc123');
});

it('generates a schema cache key', function () {
    $service = app(AICacheService::class);

    $key = $service->key('schema', 'conn_1');

    expect($key)->toBe('ai:schema:conn_1');
});

it('throws exception for unknown cache type', function () {
    $service = app(AICacheService::class);

    $service->key('unknown', 'conn_1');
})->throws(\InvalidArgumentException::class, 'Unknown cache type: unknown');

// -----------------------------------------------------------------------
//  Hash generation
// -----------------------------------------------------------------------

it('generates a deterministic schema hash from an array', function () {
    $service = app(AICacheService::class);

    $schema = ['tables' => [['name' => 'users', 'columns' => ['id', 'name']]]];
    $hash1 = $service->schemaHash($schema);
    $hash2 = $service->schemaHash($schema);

    expect($hash1)->toBe($hash2)
        ->and($hash1)->toMatch('/^[a-f0-9]{32}$/');
});

it('generates different hashes for different schemas', function () {
    $service = app(AICacheService::class);

    $hashA = $service->schemaHash(['table' => 'users']);
    $hashB = $service->schemaHash(['table' => 'posts']);

    expect($hashA)->not->toBe($hashB);
});

it('generates a deterministic message hash', function () {
    $service = app(AICacheService::class);

    $hash = $service->messageHash('  Hello, world!  ');

    expect($hash)->toBe($service->messageHash('Hello, world!'))
        ->and($hash)->toMatch('/^[a-f0-9]{32}$/');
});

// -----------------------------------------------------------------------
//  TTL configuration
// -----------------------------------------------------------------------

it('returns configured TTL for each cache type', function () {
    $service = app(AICacheService::class);

    expect($service->ttl('recommendation'))->toBe(1800)
        ->and($service->ttl('chat'))->toBe(3600)
        ->and($service->ttl('health'))->toBe(900)
        ->and($service->ttl('schema'))->toBe(300);
});

it('returns default TTL for unknown type', function () {
    $service = app(AICacheService::class);

    expect($service->ttl('unknown'))->toBe(1800);
});

// -----------------------------------------------------------------------
//  Cache operations (get / set / remember)
// -----------------------------------------------------------------------

it('stores and retrieves a value', function () {
    $service = app(AICacheService::class);
    $key = $service->key('recommendation', 'conn_test', 'hash_1');

    $service->set($key, ['score' => 85], 300);

    expect($service->get($key))->toBe(['score' => 85]);
});

it('returns null for a missing key', function () {
    $service = app(AICacheService::class);

    expect($service->get('nonexistent-key'))->toBeNull();
});

it('remembers a value via callback', function () {
    $service = app(AICacheService::class);
    $key = $service->key('recommendation', 'conn_test', 'hash_2');

    $result = $service->remember($key, fn () => ['analysis' => 'complete'], 300);

    expect($result)->toBe(['analysis' => 'complete'])
        ->and($service->get($key))->toBe(['analysis' => 'complete']);
});

it('does not re-execute callback when key exists', function () {
    $service = app(AICacheService::class);
    $key = $service->key('recommendation', 'conn_test', 'hash_3');

    $service->set($key, 'cached', 300);

    $ran = false;
    $result = $service->remember($key, function () use (&$ran) {
        $ran = true;

        return 'fresh';
    }, 300);

    expect($result)->toBe('cached')
        ->and($ran)->toBeFalse();
});

it('expires a key after its TTL', function () {
    $service = app(AICacheService::class);
    $key = $service->key('recommendation', 'conn_test', 'hash_ttl');

    $service->set($key, 'ephemeral', 1);

    expect($service->get($key))->toBe('ephemeral');

    // Advance time by 2 seconds to force expiry
    $this->travel(2)->seconds();

    expect($service->get($key))->toBeNull();
});

// -----------------------------------------------------------------------
//  Cache invalidation
// -----------------------------------------------------------------------

it('invalidates a single key', function () {
    $service = app(AICacheService::class);
    $key = $service->key('recommendation', 'conn_test', 'hash_inv');

    $service->set($key, 'data', 300);
    expect($service->get($key))->toBe('data');

    $service->invalidate($key);
    expect($service->get($key))->toBeNull();
});

it('invalidates multiple keys at once', function () {
    $service = app(AICacheService::class);
    $key1 = $service->key('recommendation', 'conn_a', 'hash_1');
    $key2 = $service->key('recommendation', 'conn_b', 'hash_2');

    $service->set($key1, 'a', 300);
    $service->set($key2, 'b', 300);

    $service->invalidate([$key1, $key2]);

    expect($service->get($key1))->toBeNull()
        ->and($service->get($key2))->toBeNull();
});

it('invalidates all cache for a connection', function () {
    $service = app(AICacheService::class);
    $connId = 'conn_42';

    $schemaKey = $service->key('schema', $connId);
    $service->set($schemaKey, ['tables' => []], 300);

    $service->invalidateConnection($connId);

    expect($service->get($schemaKey))->toBeNull();
});

// -----------------------------------------------------------------------
//  Integration-style: schema change triggers hash change
// -----------------------------------------------------------------------

it('produces different cache keys after schema change', function () {
    $service = app(AICacheService::class);

    $oldSchema = ['tables' => [['name' => 'users']]];
    $newSchema = ['tables' => [['name' => 'users'], ['name' => 'posts']]];

    $oldHash = $service->schemaHash($oldSchema);
    $newHash = $service->schemaHash($newSchema);

    $oldKey = $service->key('recommendation', 'conn_1', $oldHash);
    $newKey = $service->key('recommendation', 'conn_1', $newHash);

    expect($oldKey)->not->toBe($newKey);

    // Old schema result is cached
    $service->set($oldKey, ['score' => 75], 300);
    // New schema result is cached separately
    $service->set($newKey, ['score' => 90], 300);

    expect($service->get($oldKey))->toBe(['score' => 75])
        ->and($service->get($newKey))->toBe(['score' => 90]);
});

// -----------------------------------------------------------------------
//  Edge cases
// -----------------------------------------------------------------------

it('handles empty schema gracefully', function () {
    $service = app(AICacheService::class);

    $hash = $service->schemaHash([]);

    expect($hash)->toMatch('/^[a-f0-9]{32}$/');
});

it('handles boolean and null values', function () {
    $service = app(AICacheService::class);
    $key = $service->key('health', 'conn_1', 'hash_bool');

    $service->set($key, true, 300);
    expect($service->get($key))->toBeTrue();

    $service->set($key, null, 300);
    expect($service->get($key))->toBeNull();
});
