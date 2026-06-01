<?php

declare(strict_types=1);

use App\Modules\Query\DTOs\SlowQueryDTO;
use App\Modules\Query\Services\SlowQueryReader;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

// ---------------------------------------------------------------------------
// SlowQueryDTO Tests
// ---------------------------------------------------------------------------

describe('SlowQueryDTO', function () {
    it('can be created from array', function () {
        $dto = SlowQueryDTO::fromArray([
            'digest' => 'abc123',
            'query_text' => 'SELECT * FROM users',
            'avg_timer_wait' => 2.5,
            'rows_examined_avg' => 5000,
            'last_seen' => '2026-01-15 10:30:00',
            'frequency' => 42,
        ]);

        expect($dto)->toBeInstanceOf(SlowQueryDTO::class);
        expect($dto->digest)->toBe('abc123');
        expect($dto->queryText)->toBe('SELECT * FROM users');
        expect($dto->avgTimerWait)->toBe(2.5);
        expect($dto->rowsExaminedAvg)->toBe(5000);
        expect($dto->lastSeen)->toBe('2026-01-15 10:30:00');
        expect($dto->frequency)->toBe(42);
    });

    it('converts to array', function () {
        $dto = SlowQueryDTO::fromArray([
            'digest' => 'def456',
            'query_text' => 'SELECT id, name FROM products WHERE price > 100',
            'avg_timer_wait' => 1.234,
            'rows_examined_avg' => 10000,
            'last_seen' => '2026-02-20 14:00:00',
            'frequency' => 7,
        ]);

        $array = $dto->toArray();

        expect($array['digest'])->toBe('def456');
        expect($array['query_text'])->toBe('SELECT id, name FROM products WHERE price > 100');
        expect($array['avg_timer_wait'])->toBe(1.234);
        expect($array['rows_examined_avg'])->toBe(10000);
        expect($array['last_seen'])->toBe('2026-02-20 14:00:00');
        expect($array['frequency'])->toBe(7);
    });

    it('applies default values for missing fields', function () {
        $dto = SlowQueryDTO::fromArray([
            'digest' => 'xyz789',
            'query_text' => 'SELECT 1',
            'last_seen' => '2026-03-01 00:00:00',
        ]);

        expect($dto->avgTimerWait)->toBe(0.0);
        expect($dto->rowsExaminedAvg)->toBe(0);
        expect($dto->frequency)->toBe(1);
    });

    it('is immutable', function () {
        $dto = SlowQueryDTO::fromArray([
            'digest' => 'immutable',
            'query_text' => 'SELECT * FROM test',
            'avg_timer_wait' => 0.5,
            'rows_examined_avg' => 100,
            'last_seen' => '2026-04-01 12:00:00',
            'frequency' => 3,
        ]);

        expect(fn () => $dto->digest = 'changed')->toThrow(Error::class);
    });
});

// ---------------------------------------------------------------------------
// SlowQueryReader Tests
// ---------------------------------------------------------------------------

describe('SlowQueryReader', function () {
    // Helper: create a reader with mocked dependencies for unit tests
    function createReader(): SlowQueryReader
    {
        $repo = Mockery::mock('App\Modules\Connection\Repositories\ConnectionRepository');
        $encryptor = Mockery::mock('App\Modules\Connection\Services\ConnectionEncryptor');

        return new SlowQueryReader($repo, $encryptor);
    }

    describe('slow log parsing', function () {
        it('parses a standard MySQL slow log entry', function () {
            $reader = createReader();
            $reflection = new ReflectionClass($reader);
            $method = $reflection->getMethod('parseSlowLogContent');
            $method->setAccessible(true);

            $logContent = '# Time: 2026-01-15T10:30:00.123456Z
# User@Host: root[root] @ localhost []  Id:  1234
# Query_time: 2.345678  Lock_time: 0.000123  Rows_sent: 10  Rows_examined: 5000
SET timestamp=1705312200;
SELECT * FROM large_table WHERE status = "active" ORDER BY created_at DESC;
';

            $result = $method->invoke($reader, $logContent);

            expect($result)->toBeArray();
            expect($result)->toHaveCount(1);

            $dto = $result[0];
            expect($dto)->toBeInstanceOf(SlowQueryDTO::class);
            expect($dto->queryText)->toContain('SELECT * FROM large_table');
            expect($dto->avgTimerWait)->toBe(2.345678);
            expect($dto->rowsExaminedAvg)->toBe(5000);
            expect($dto->lastSeen)->toContain('2026-01-15');
        });

        it('parses multiple slow log entries', function () {
            $reader = createReader();
            $reflection = new ReflectionClass($reader);
            $method = $reflection->getMethod('parseSlowLogContent');
            $method->setAccessible(true);

            $logContent = '# Time: 2026-01-15T10:30:00.123456Z
# User@Host: app[app] @ localhost []  Id:  100
# Query_time: 1.500000  Lock_time: 0.000050  Rows_sent: 5  Rows_examined: 2000
SELECT * FROM orders WHERE status = "pending";
# Time: 2026-01-15T10:31:00.654321Z
# User@Host: app[app] @ localhost []  Id:  101
# Query_time: 3.200000  Lock_time: 0.000100  Rows_sent: 50  Rows_examined: 15000
SELECT * FROM products JOIN inventory ON products.id = inventory.product_id;
';

            $result = $method->invoke($reader, $logContent);

            expect($result)->toHaveCount(2);

            expect($result[0]->avgTimerWait)->toBe(1.5);
            expect($result[0]->rowsExaminedAvg)->toBe(2000);
            expect($result[0]->queryText)->toContain('SELECT * FROM orders');

            expect($result[1]->avgTimerWait)->toBe(3.2);
            expect($result[1]->rowsExaminedAvg)->toBe(15000);
            expect($result[1]->queryText)->toContain('SELECT * FROM products');
        });

        it('returns empty array for empty log content', function () {
            $reader = createReader();
            $reflection = new ReflectionClass($reader);
            $method = $reflection->getMethod('parseSlowLogContent');
            $method->setAccessible(true);

            expect($method->invoke($reader, ''))->toBe([]);
        });

        it('returns empty array for log content with no query entries', function () {
            $reader = createReader();
            $reflection = new ReflectionClass($reader);
            $method = $reflection->getMethod('parseSlowLogContent');
            $method->setAccessible(true);

            $logContent = '# Just a comment line
# Not a valid slow query entry
Some random text without proper format';
            expect($method->invoke($reader, $logContent))->toBe([]);
        });

        it('generates a digest for slow log entries', function () {
            $reader = createReader();
            $reflection = new ReflectionClass($reader);
            $method = $reflection->getMethod('parseSlowLogContent');
            $method->setAccessible(true);

            $logContent = '# Time: 2026-01-15T10:30:00.123456Z
# User@Host: root[root] @ localhost []  Id:  1
# Query_time: 0.500000  Lock_time: 0.000010  Rows_sent: 1  Rows_examined: 100
SELECT COUNT(*) FROM users;
';

            $result = $method->invoke($reader, $logContent);
            expect($result)->toHaveCount(1);

            $dto = $result[0];
            expect($dto->digest)->toStartWith('slow_log_');
            expect(strlen($dto->digest))->toBe(9 + 32); // 'slow_log_' + 32 hex chars
        });
    });

    describe('readFromSlowLog', function () {
        it('returns empty array for non-existent file', function () {
            $reader = createReader();

            $result = $reader->readFromSlowLog('conn-1', '/tmp/nonexistent-slow-log.log');

            expect($result)->toBe([]);
        });
    });

    describe('getTopSlowQueries', function () {
        it('respects the limit parameter', function () {
            $reader = Mockery::mock(SlowQueryReader::class)
                ->makePartial()
                ->shouldAllowMockingProtectedMethods();

            $dto1 = new SlowQueryDTO('a', 'SELECT 1', 1.0, 10, '2026-01-01', 1);
            $dto2 = new SlowQueryDTO('b', 'SELECT 2', 5.0, 20, '2026-01-01', 1);
            $dto3 = new SlowQueryDTO('c', 'SELECT 3', 3.0, 30, '2026-01-01', 1);

            $reader->shouldReceive('readFromPerformanceSchema')
                ->once()
                ->with('conn-1')
                ->andReturn([$dto1, $dto2, $dto3]);

            $result = $reader->getTopSlowQueries('conn-1', 2);

            expect($result)->toHaveCount(2);
            // Should be sorted by avgTimerWait DESC
            expect($result[0]->avgTimerWait)->toBe(5.0);
            expect($result[1]->avgTimerWait)->toBe(3.0);
        });
    });

    describe('analyzeSlowQueries', function () {
        it('returns analysis results with matched tables', function () {
            $reader = createReader();

            $table1 = new TableDTO(
                name: 'users',
                columns: [],
                indexes: [],
                rowCount: 0,
                sizeMb: 0.0,
                comment: null,
            );
            $table2 = new TableDTO(
                name: 'orders',
                columns: [],
                indexes: [],
                rowCount: 0,
                sizeMb: 0.0,
                comment: null,
            );

            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [$table1, $table2],
                relations: [],
                summary: ['total_tables' => 2],
            );

            $queries = [
                new SlowQueryDTO('d1', 'SELECT * FROM users WHERE id = 1', 0.5, 100, '2026-01-01', 1),
                new SlowQueryDTO('d2', 'SELECT * FROM orders JOIN users ON ...', 2.5, 50000, '2026-01-01', 200),
            ];

            $results = $reader->analyzeSlowQueries($queries, $context);

            expect($results)->toHaveCount(2);
            expect($results[0]['database'])->toBe('test_db');
            expect($results[0]['matched_tables'])->toContain('users');
            expect($results[0]['matched_tables'])->not->toContain('orders');

            expect($results[1]['matched_tables'])->toContain('orders');
            expect($results[1]['matched_tables'])->toContain('users');

            // The second query is slow and high frequency — should have suggestions
            expect($results[1]['suggestion'])->toContain('>1s');
            expect($results[1]['suggestion'])->toContain('50000');
            expect($results[1]['suggestion'])->toContain('200');
        });

        it('returns empty suggestions for fast, infrequent queries', function () {
            $reader = createReader();

            $context = new SchemaContextDTO(
                database: 'test_db',
                tables: [],
                relations: [],
                summary: [],
            );

            $queries = [
                new SlowQueryDTO('d1', 'SELECT 1', 0.01, 5, '2026-01-01', 1),
            ];

            $results = $reader->analyzeSlowQueries($queries, $context);

            expect($results[0]['suggestion'])->toBe('No optimization suggestions');
        });
    });
});
