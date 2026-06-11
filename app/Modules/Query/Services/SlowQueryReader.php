<?php

declare(strict_types=1);

namespace App\Modules\Query\Services;

use App\Modules\Connection\Repositories\ConnectionRepository;
use App\Modules\Connection\Services\ConnectionEncryptor;
use App\Modules\Query\DTOs\SlowQueryDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use Doctrine\DBAL\DriverManager;
use Illuminate\Support\Facades\Log;

class SlowQueryReader
{
    private const int DEFAULT_LIMIT = 50;

    public function __construct(
        private readonly ConnectionRepository $connectionRepo,
        private readonly ConnectionEncryptor $encryptor,
    ) {}

    /**
     * Read slow queries from MySQL performance_schema.
     *
     * @return array<int, SlowQueryDTO>
     */
    public function readFromPerformanceSchema(string $connectionId): array
    {
        try {
            $connection = $this->connectionRepo->findById($connectionId);

            if (! $connection) {
                $this->safeLog(fn () => Log::warning('SlowQueryReader: Connection not found', ['connection_id' => $connectionId]));

                return [];
            }

            $config = $this->buildConnectionConfig($connection);

            $conn = DriverManager::getConnection($config);

            $sql = '
                SELECT
                    DIGEST AS digest,
                    DIGEST_TEXT AS query_text,
                    SUM_TIMER_WAIT / 1000000000000 AS avg_timer_wait,
                    ROWS_EXAMINED_AVG AS rows_examined_avg,
                    MAX(FIRST_SEEN) AS last_seen,
                    COUNT_STAR AS frequency
                FROM performance_schema.events_statements_summary_by_digest
                WHERE DIGEST IS NOT NULL
                GROUP BY DIGEST, DIGEST_TEXT
                ORDER BY SUM_TIMER_WAIT DESC
                LIMIT '.self::DEFAULT_LIMIT;

            $rows = $conn->executeQuery($sql)->fetchAllAssociative();

            $conn->close();

            return array_map(
                fn (array $row) => SlowQueryDTO::fromArray([
                    'digest' => $row['digest'],
                    'query_text' => $row['query_text'],
                    'avg_timer_wait' => (float) $row['avg_timer_wait'],
                    'rows_examined_avg' => (int) $row['rows_examined_avg'],
                    'last_seen' => $row['last_seen'],
                    'frequency' => (int) $row['frequency'],
                ]),
                $rows,
            );
        } catch (\Throwable $e) {
            $this->safeLog(fn () => Log::error('SlowQueryReader: Failed to read from performance_schema', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]));

            return [];
        }
    }

    /**
     * Read slow queries from MySQL slow query log file.
     *
     * @return array<int, SlowQueryDTO>
     */
    public function readFromSlowLog(string $connectionId, string $logPath): array
    {
        if (! file_exists($logPath) || ! is_readable($logPath)) {
            $this->safeLog(fn () => Log::warning('SlowQueryReader: Slow log file not found or unreadable', [
                'connection_id' => $connectionId,
                'log_path' => $logPath,
            ]));

            return [];
        }

        try {
            $content = file_get_contents($logPath);

            if ($content === false || $content === '') {
                return [];
            }

            return $this->parseSlowLogContent($content);
        } catch (\Throwable $e) {
            $this->safeLog(fn () => Log::error('SlowQueryReader: Failed to read slow log file', [
                'connection_id' => $connectionId,
                'log_path' => $logPath,
                'error' => $e->getMessage(),
            ]));

            return [];
        }
    }

    /**
     * Analyze slow queries with schema context.
     *
     * @param  array<int, SlowQueryDTO>  $queries
     * @return array<int, array<string, mixed>>
     */
    public function analyzeSlowQueries(array $queries, SchemaContextDTO $context): array
    {
        $tableNames = array_map(
            fn ($table) => $table->name,
            $context->tables,
        );

        $results = [];

        foreach ($queries as $query) {
            $matchedTables = $this->findReferencedTables($query->queryText, $tableNames);

            $suggestion = $this->generateSuggestion($query, $matchedTables);

            $results[] = [
                'query' => $query->toArray(),
                'matched_tables' => $matchedTables,
                'suggestion' => $suggestion,
                'database' => $context->database,
            ];
        }

        return $results;
    }

    /**
     * Get top slow queries from performance_schema.
     *
     * @return array<int, SlowQueryDTO>
     */
    public function getTopSlowQueries(string $connectionId, int $limit = 50): array
    {
        $queries = $this->readFromPerformanceSchema($connectionId);

        usort($queries, fn (SlowQueryDTO $a, SlowQueryDTO $b) => $b->avgTimerWait <=> $a->avgTimerWait);

        return array_slice($queries, 0, max(1, $limit));
    }

    /**
     * Safely call a logging closure without crashing if facades aren't booted.
     */
    private function safeLog(callable $logFn): void
    {
        try {
            $logFn();
        } catch (\Throwable) {
            // Silently ignore — facades may not be available in unit test environments
        }
    }

    /**
     * Build Doctrine DBAL connection config from the connection model.
     */
    private function buildConnectionConfig(mixed $connection): array
    {
        $driverMap = [
            'mysql' => 'pdo_mysql',
            'mariadb' => 'pdo_mysql',
        ];

        $config = [
            'driver' => $driverMap[$connection->driver] ?? 'pdo_mysql',
            'host' => $connection->host,
            'port' => (int) $connection->port,
            'dbname' => $connection->database,
            'user' => $connection->username,
            'password' => $this->encryptor->decrypt($connection->password),
            'charset' => 'utf8mb4',
        ];

        if ($connection->ssl_enabled) {
            $config['sslmode'] = 'prefer';
        }

        return $config;
    }

    /**
     * Parse MySQL slow query log content into SlowQueryDTO objects.
     *
     * @return array<int, SlowQueryDTO>
     */
    private function parseSlowLogContent(string $content): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $currentEntry = null;
        $currentSql = '';

        foreach ($lines as $line) {
            // Start of a new slow query entry
            if (preg_match('/^#\s+Time:\s+(.+)$/i', $line, $m)) {
                if ($currentEntry !== null && $currentSql !== '') {
                    $currentEntry['sql_text'] = trim($currentSql);
                    $entries[] = $this->buildDtoFromSlowLogEntry($currentEntry);
                }

                $currentEntry = ['timestamp' => trim($m[1])];
                $currentSql = '';
            } elseif ($currentEntry !== null && preg_match(
                '/^#\s+Query_time:\s+([\d.]+)\s+Lock_time:\s+([\d.]+)\s+(?:Rows_sent:\s+\d+\s+)?Rows_examined:\s+(\d+)/i',
                $line,
                $m,
            )) {
                $currentEntry['query_time'] = (float) $m[1];
                $currentEntry['lock_time'] = (float) $m[2];
                $currentEntry['rows_examined'] = (int) $m[3];
            } elseif ($currentEntry !== null && ! str_starts_with($line, '#') && trim($line) !== '') {
                $currentSql .= $line."\n";
            }
        }

        // Don't forget the last entry
        if ($currentEntry !== null && $currentSql !== '') {
            $currentEntry['sql_text'] = trim($currentSql);
            $entries[] = $this->buildDtoFromSlowLogEntry($currentEntry);
        }

        return $entries;
    }

    /**
     * Build a SlowQueryDTO from a parsed slow log entry.
     */
    private function buildDtoFromSlowLogEntry(array $entry): SlowQueryDTO
    {
        $queryText = $entry['sql_text'] ?? '';
        $digest = 'slow_log_'.md5($queryText);

        return new SlowQueryDTO(
            digest: $digest,
            queryText: $queryText,
            avgTimerWait: $entry['query_time'] ?? 0.0,
            rowsExaminedAvg: $entry['rows_examined'] ?? 0,
            lastSeen: $entry['timestamp'] ?? date('Y-m-d H:i:s'),
            frequency: 1,
        );
    }

    /**
     * Find table names referenced in a query.
     *
     * @param  array<int, string>  $tableNames
     * @return array<int, string>
     */
    private function findReferencedTables(string $queryText, array $tableNames): array
    {
        $matched = [];

        foreach ($tableNames as $table) {
            $quoted = preg_quote($table, '/');

            if (preg_match('/\b'.$quoted.'\b/i', $queryText)) {
                $matched[] = $table;
            }
        }

        return $matched;
    }

    /**
     * Generate a human-readable suggestion based on slow query analysis.
     *
     * @param  array<int, string>  $matchedTables
     */
    private function generateSuggestion(SlowQueryDTO $query, array $matchedTables): string
    {
        $suggestions = [];

        if ($query->avgTimerWait > 1.0) {
            $suggestions[] = 'Query takes >1s avg — consider indexing or query optimization';
        }

        if ($query->rowsExaminedAvg > 10000) {
            $suggestions[] = sprintf('High rows examined (%d avg) — add appropriate indexes', $query->rowsExaminedAvg);
        }

        if ($query->frequency > 100) {
            $suggestions[] = sprintf('High frequency (%d executions) — consider caching or reducing calls', $query->frequency);
        }

        if (! empty($matchedTables)) {
            $suggestions[] = 'Referenced tables: '.implode(', ', $matchedTables);
        }

        return ! empty($suggestions)
            ? implode('; ', $suggestions)
            : 'No optimization suggestions';
    }
}
