<?php

declare(strict_types=1);

namespace App\Modules\Query\Services;

use App\Modules\Connection\Repositories\ConnectionRepository;
use App\Modules\Connection\Services\ConnectionEncryptor;
use App\Modules\Query\DTOs\ExplainNodeDTO;
use App\Modules\Query\DTOs\ExplainResultDTO;
use Doctrine\DBAL\DriverManager;

class ExplainAnalyzer
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepo,
        private readonly ConnectionEncryptor $encryptor,
    ) {}

    /**
     * Analyze a query using EXPLAIN FORMAT=JSON.
     */
    public function analyze(string $query, string $connectionId): ExplainResultDTO
    {
        $connection = $this->connectionRepo->findById($connectionId);

        if (! $connection) {
            throw new \RuntimeException('Connection not found');
        }

        $driverMap = ['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql'];
        $config = [
            'driver' => $driverMap[$connection->driver] ?? 'pdo_mysql',
            'dbname' => $connection->database,
            'user' => $connection->username,
            'password' => $this->encryptor->decrypt($connection->password),
            'charset' => 'utf8mb4',
        ];

        // Use unix_socket for local connections
        if (empty($connection->host) || $connection->host === 'localhost' || $connection->host === '127.0.0.1') {
            $socketPath = '/var/run/mysqld/mysqld.sock';
            if (file_exists($socketPath)) {
                $config['unix_socket'] = $socketPath;
            } else {
                $config['host'] = $connection->host ?: '127.0.0.1';
                $config['port'] = (int) ($connection->port ?: 3306);
            }
        } else {
            $config['host'] = $connection->host;
            $config['port'] = (int) ($connection->port ?: 3306);
        }

        $conn = DriverManager::getConnection($config);

        try {
            $explainSql = 'EXPLAIN FORMAT=JSON '.$query;
            $stmt = $conn->executeQuery($explainSql);
            $row = $stmt->fetchAssociative();

            if (! $row || ! isset($row['EXPLAIN'])) {
                throw new \RuntimeException('EXPLAIN did not return expected JSON output');
            }

            /** @var array<string, mixed> $explainJson */
            $explainJson = json_decode($row['EXPLAIN'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to parse EXPLAIN JSON: '.json_last_error_msg());
            }

            $tree = $this->formatTree($explainJson);
            $costBreakdown = $this->getCostBreakdown($explainJson);
            $result = new ExplainResultDTO(
                query: $query,
                tree: $tree,
                costBreakdown: $costBreakdown,
                suggestions: [],
                raw: $explainJson,
            );

            $suggestions = $this->suggestOptimizations($result);

            return new ExplainResultDTO(
                query: $query,
                tree: $tree,
                costBreakdown: $costBreakdown,
                suggestions: $suggestions,
                raw: $explainJson,
            );
        } finally {
            $conn->close();
        }
    }

    /**
     * Parse EXPLAIN FORMAT=JSON into a structured tree of ExplainNodeDTO.
     *
     * @param  array<string, mixed>  $explainJson
     * @return ExplainNodeDTO[]
     */
    public function formatTree(array $explainJson): array
    {
        $nodes = [];

        if (isset($explainJson['query_block'])) {
            $nodes[] = $this->parseQueryBlock($explainJson['query_block'], '1');
        } elseif (isset($explainJson['query'])) {
            // Handle UNION or other top-level structures
            $nodes[] = $this->parseQueryBlock($explainJson['query'], '1');
        }

        return $nodes;
    }

    /**
     * Parse a single query_block.
     *
     * @param  array<string, mixed>  $block
     */
    private function parseQueryBlock(array $block, string $prefix): ExplainNodeDTO
    {
        $selectId = $block['select_id'] ?? $prefix;
        $costInfo = $block['cost_info'] ?? [];
        $totalCost = $this->parseCost($costInfo['query_cost'] ?? '0');
        $nodeId = "query_block_{$selectId}";

        $children = [];

        // Handle nested_loop (JOIN operations)
        if (isset($block['nested_loop'])) {
            foreach ($block['nested_loop'] as $i => $loopItem) {
                $childPrefix = "{$prefix}.{$i}";
                $children[] = $this->parseNestedLoopItem($loopItem, $childPrefix);
            }
        }

        // Handle single table (no JOIN)
        if (isset($block['table'])) {
            $children[] = $this->parseTableNode($block['table'], "{$prefix}.0");
        }

        // Handle subqueries (e.g., "select #2" subquery in WHERE)
        foreach ($block as $key => $value) {
            if (str_starts_with((string) $key, 'select_id')) {
                continue;
            }
            if (is_array($value) && isset($value['select_id'])) {
                $childPrefix = "{$prefix}.sub";
                $children[] = $this->parseQueryBlock($value, $childPrefix);
            }
        }

        // Handle grouping_operation (GROUP BY with temp table)
        if (isset($block['grouping_operation'])) {
            $childPrefix = "{$prefix}.group";
            $children[] = $this->parseQueryBlock($block['grouping_operation'], $childPrefix);
        }

        // Handle duplicates (temporary table for DISTINCT)
        if (isset($block['duplicates_removal'])) {
            $childPrefix = "{$prefix}.distinct";
            $children[] = $this->parseQueryBlock($block['duplicates_removal'], $childPrefix);
        }

        // Handle UNION result
        if (isset($block['union_result'])) {
            $childPrefix = "{$prefix}.union";
            $children[] = $this->parseTableNode($block['union_result'], $childPrefix);
        }

        // Handle materialized subqueries
        if (isset($block['materialized_from_subquery'])) {
            $childPrefix = "{$prefix}.materialized";
            $children[] = $this->parseQueryBlock($block['materialized_from_subquery'], $childPrefix);
        }

        // Get all table names from children
        $tableNames = array_filter(array_map(fn (ExplainNodeDTO $c) => $c->table, $children));

        return new ExplainNodeDTO(
            id: $nodeId,
            type: 'query_block',
            table: ! empty($tableNames) ? implode(', ', $tableNames) : null,
            cost: $totalCost,
            rows: (int) ($block['rows'] ?? 0),
            filtered: $this->parsePercentage($block['filtered'] ?? '100'),
            accessType: $this->inferQueryBlockAccessType($children),
            key: null,
            extra: null,
            costInfo: $costInfo,
            usedColumns: [],
            children: $children,
            extraFields: [
                'select_id' => (int) $selectId,
                'message' => $block['message'] ?? null,
            ],
        );
    }

    /**
     * Parse a nested_loop item (can be a table, another nested_loop, or a subquery).
     *
     * @param  array<string, mixed>  $item
     */
    private function parseNestedLoopItem(array $item, string $prefix): ExplainNodeDTO
    {
        if (isset($item['table'])) {
            return $this->parseTableNode($item['table'], $prefix);
        }

        // Nested nested_loop (happens with complex joins)
        if (isset($item['nested_loop'])) {
            $children = [];
            foreach ($item['nested_loop'] as $i => $subItem) {
                $children[] = $this->parseNestedLoopItem($subItem, "{$prefix}.{$i}");
            }

            $totalCost = array_sum(array_map(fn (ExplainNodeDTO $c) => $c->cost, $children));
            $totalRows = array_sum(array_map(fn (ExplainNodeDTO $c) => $c->rows, $children));

            return new ExplainNodeDTO(
                id: "nested_loop_{$prefix}",
                type: 'nested_loop',
                table: null,
                cost: $totalCost,
                rows: $totalRows,
                filtered: 100.0,
                accessType: 'nested_loop',
                key: null,
                extra: null,
                costInfo: [],
                usedColumns: [],
                children: $children,
            );
        }

        // Subquery in nested_loop
        if (isset($item['select_id'])) {
            return $this->parseQueryBlock($item, $prefix);
        }

        // Unknown structure, create a generic node
        return new ExplainNodeDTO(
            id: "unknown_{$prefix}",
            type: 'unknown',
            table: null,
            cost: 0,
            rows: 0,
            filtered: 100.0,
            accessType: 'unknown',
            key: null,
            extra: null,
            costInfo: [],
            usedColumns: [],
            children: [],
        );
    }

    /**
     * Parse a table node (leaf in the EXPLAIN tree).
     *
     * @param  array<string, mixed>  $table
     */
    private function parseTableNode(array $table, string $prefix): ExplainNodeDTO
    {
        $costInfo = $table['cost_info'] ?? [];
        $prefixCost = $this->parseCost($costInfo['prefix_cost'] ?? '0');

        $accessType = strtoupper($table['access_type'] ?? 'ALL');

        // Parse filtered percentage
        $filtered = $this->parsePercentage($table['filtered'] ?? '100');

        // Parse extra info
        $extra = $this->parseExtra($table);

        // Parse used columns
        $usedColumns = $table['used_columns'] ?? $table['used_key_parts'] ?? [];

        // Handle subqueries attached to this table (e.g., "used_partitions")
        $children = [];

        // Detect subqueries within the table node (e.g., materialized)
        if (isset($table['materialized_from_subquery'])) {
            $children[] = $this->parseQueryBlock($table['materialized_from_subquery'], "{$prefix}.mat");
        }

        return new ExplainNodeDTO(
            id: "table_{$prefix}",
            type: 'table',
            table: $table['table_name'] ?? null,
            cost: $prefixCost,
            rows: (int) ($table['rows_examined_per_scan'] ?? $table['rows_produced_per_join'] ?? 0),
            filtered: $filtered,
            accessType: $accessType,
            key: $table['key'] ?? $table['used_key_parts'][0] ?? null,
            extra: $extra,
            costInfo: $costInfo,
            usedColumns: $usedColumns,
            children: $children,
            extraFields: [
                'rows_examined_per_scan' => $table['rows_examined_per_scan'] ?? null,
                'rows_produced_per_join' => $table['rows_produced_per_join'] ?? null,
                'used_key_parts' => $table['used_key_parts'] ?? [],
                'used_partitions' => $table['partitions_used'] ?? $table['used_partitions'] ?? [],
                'attached_condition' => $table['attached_condition'] ?? null,
                'attached_subqueries' => $table['attached_subqueries'] ?? [],
                'possible_keys' => $table['possible_keys'] ?? [],
                'key_len' => $table['key_length'] ?? $table['key_len'] ?? null,
                'ref' => $table['ref'] ?? [],
            ],
        );
    }

    /**
     * Parse cost string (e.g., "1.00" or "2.50") to float.
     */
    private function parseCost(string $cost): float
    {
        return (float) str_replace(',', '', $cost);
    }

    /**
     * Parse percentage string (e.g., "100.00") to float.
     */
    private function parsePercentage(string $pct): float
    {
        return (float) $pct;
    }

    /**
     * Parse the "Extra" field from a table node.
     */
    private function parseExtra(array $table): ?string
    {
        // MySQL 8.0+ puts extra info in a separate "extra" field
        // Also check for various flags
        if (isset($table['extra'])) {
            return $table['extra'];
        }

        $parts = [];
        $flags = [
            'using_index' => 'Using index',
            'using_where' => 'Using where',
            'using_temporary' => 'Using temporary',
            'using_filesort' => 'Using filesort',
            'using_join_buffer' => 'Using join buffer (hash join)',
            'using_index_condition' => 'Using index condition',
            'using_union' => 'Using union',
            'using_intersect' => 'Using intersect',
            'full_text_search' => 'Full-text search',
            'using_mrr' => 'Using MRR',
            'using_materialized' => 'Using materialized',
            'no_matching_rows' => 'No matching rows (const)',
            'no_matching_rows_in_const_table' => 'No matching rows in const table',
            'impossible_where' => 'Impossible WHERE',
            'select_tables_optimized_away' => 'Select tables optimized away',
            'distinct' => 'Distinct',
            'not_exists' => 'Not exists',
            'range_checked_for_each_record' => 'Range checked for each record',
            'using_index_for_group_by' => 'Using index for group-by',
            'using_index_for_skip_scan' => 'Using index for skip scan',
            'using_secondary_engine' => 'Using secondary engine',
            'using_full_text_search' => 'Using full-text search',
        ];

        foreach ($flags as $key => $label) {
            if (! empty($table[$key])) {
                $parts[] = $label;
            }
        }

        return ! empty($parts) ? implode('; ', $parts) : null;
    }

    /**
     * Infer the overall access type for a query block from its children.
     *
     * @param  ExplainNodeDTO[]  $children
     */
    private function inferQueryBlockAccessType(array $children): string
    {
        if (empty($children)) {
            return 'ALL';
        }

        $worst = 'const';
        $order = ['const' => 0, 'eq_ref' => 1, 'ref' => 2, 'range' => 3, 'index' => 4, 'ALL' => 5, 'unknown' => 6];

        foreach ($children as $child) {
            $childAccess = $child->accessType;
            $childRank = $order[$childAccess] ?? 6;
            $worstRank = $order[$worst] ?? 0;

            if ($childRank > $worstRank) {
                $worst = $childAccess;
            }
        }

        return $worst;
    }

    /**
     * Extract cost breakdown from the EXPLAIN JSON.
     *
     * @param  array<string, mixed>  $explainJson
     * @return array<string, mixed>
     */
    public function getCostBreakdown(array $explainJson): array
    {
        $breakdown = [
            'total_query_cost' => 0.0,
            'tables' => [],
            'operations' => [],
        ];

        if (isset($explainJson['query_block']['cost_info']['query_cost'])) {
            $breakdown['total_query_cost'] = $this->parseCost(
                $explainJson['query_block']['cost_info']['query_cost']
            );
        }

        $this->collectCosts($explainJson, $breakdown);

        return $breakdown;
    }

    /**
     * Recursively collect costs from the EXPLAIN tree.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $breakdown
     */
    private function collectCosts(array $node, array &$breakdown): void
    {
        // Handle query_block
        if (isset($node['query_block'])) {
            $this->collectCosts($node['query_block'], $breakdown);

            return;
        }

        // Handle nested table key (query_block > table)
        if (isset($node['table']) && is_array($node['table'])) {
            $this->collectCosts($node['table'], $breakdown);
        }

        // Handle table node (by table_name)
        if (isset($node['table_name'])) {
            $costInfo = $node['cost_info'] ?? [];
            $prefixCost = $this->parseCost($costInfo['prefix_cost'] ?? '0');
            $readCost = $this->parseCost($costInfo['read_cost'] ?? '0');
            $evalCost = $this->parseCost($costInfo['eval_cost'] ?? '0');

            $breakdown['tables'][] = [
                'table' => $node['table_name'],
                'access_type' => strtoupper($node['access_type'] ?? 'ALL'),
                'rows_examined' => (int) ($node['rows_examined_per_scan'] ?? 0),
                'rows_produced' => (int) ($node['rows_produced_per_join'] ?? 0),
                'filtered' => (float) ($node['filtered'] ?? 100),
                'cost' => [
                    'prefix_cost' => $prefixCost,
                    'read_cost' => $readCost,
                    'eval_cost' => $evalCost,
                    'data_read_per_join' => $costInfo['data_read_per_join'] ?? null,
                ],
                'key' => $node['key'] ?? null,
                'using_index' => ! empty($node['using_index']),
            ];

            $breakdown['operations'][] = [
                'type' => 'table_access',
                'table' => $node['table_name'],
                'access_type' => strtoupper($node['access_type'] ?? 'ALL'),
                'cost' => $prefixCost,
            ];

            return;
        }

        // Handle nested_loop
        if (isset($node['nested_loop'])) {
            foreach ($node['nested_loop'] as $item) {
                $itemTable = $item['table'] ?? null;
                $breakdown['operations'][] = [
                    'type' => 'nested_loop',
                    'table' => $itemTable['table_name'] ?? null,
                    'cost' => $this->parseCost(
                        $itemTable['cost_info']['prefix_cost'] ?? $itemTable['cost_info']['read_cost'] ?? '0'
                    ),
                ];
                $this->collectCosts($item, $breakdown);
            }
        }

        // Handle grouping
        if (isset($node['grouping_operation'])) {
            $breakdown['operations'][] = ['type' => 'grouping_operation', 'cost' => 0];
            $this->collectCosts($node['grouping_operation'], $breakdown);
        }

        // Handle duplicates removal
        if (isset($node['duplicates_removal'])) {
            $breakdown['operations'][] = ['type' => 'distinct', 'cost' => 0];
            $this->collectCosts($node['duplicates_removal'], $breakdown);
        }

        // Handle union
        if (isset($node['union_result'])) {
            $breakdown['operations'][] = ['type' => 'union', 'cost' => 0];
            $this->collectCosts($node['union_result'], $breakdown);
        }

        // Handle materialized subquery
        if (isset($node['materialized_from_subquery'])) {
            $breakdown['operations'][] = ['type' => 'materialized_subquery', 'cost' => 0];
            $this->collectCosts($node['materialized_from_subquery'], $breakdown);
        }
    }

    /**
     * Generate optimization suggestions based on the EXPLAIN analysis.
     *
     * @return string[]
     */
    public function suggestOptimizations(ExplainResultDTO $result): array
    {
        $suggestions = [];

        foreach ($result->tree as $root) {
            $this->collectSuggestions($root, $suggestions);
        }

        // Filter duplicates
        $suggestions = array_values(array_unique($suggestions));

        // Enrich with cost context
        if ($result->costBreakdown['total_query_cost'] > 1000) {
            array_unshift($suggestions, '⚠ High query cost ('.round($result->costBreakdown['total_query_cost'], 2).'). Consider query optimization.');
        }

        return $suggestions;
    }

    /**
     * Recursively collect optimization suggestions from the tree.
     *
     * @param  string[]  $suggestions
     */
    private function collectSuggestions(ExplainNodeDTO $node, array &$suggestions): void
    {
        switch ($node->accessType) {
            case 'ALL':
                $table = $node->table ?? 'unknown';
                $suggestions[] = "Full table scan on `{$table}` — add index or optimize WHERE clause.";
                break;

            case 'INDEX':
            case 'index':
                $table = $node->table ?? 'unknown';
                $suggestions[] = "Index scan on `{$table}` — consider covering index to avoid table lookups.";
                break;

            case 'REF':
            case 'ref':
                if ($node->filtered < 10) {
                    $table = $node->table ?? 'unknown';
                    $suggestions[] = "Low selectivity on `{$table}` (filtered: {$node->filtered}%) — consider composite index.";
                }
                break;
        }

        // Check for filesort
        if ($node->extra && str_contains($node->extra, 'Using filesort')) {
            $table = $node->table ?? 'unknown';
            $suggestions[] = "Using filesort on `{$table}` — add index on ORDER BY columns.";
        }

        // Check for temporary table
        if ($node->extra && str_contains($node->extra, 'Using temporary')) {
            $suggestions[] = 'Using temporary table — optimize GROUP BY or DISTINCT with appropriate indexes.';
        }

        // Check for full table scan with high row count
        if ($node->accessType === 'ALL' && $node->rows > 10000) {
            $table = $node->table ?? 'unknown';
            $suggestions[] = "Large full table scan on `{$table}` ({$node->rows} rows examined) — critical to add index.";
        }

        // Recurse into children
        foreach ($node->children as $child) {
            $this->collectSuggestions($child, $suggestions);
        }
    }
}
