<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

/**
 * OptimizationAgent — identifies index optimization opportunities, missing indexes,
 * duplicate indexes, tables without primary keys, and tables at risk of full table scans.
 *
 * Uses deterministic rule-based detection (zero API cost).
 */
class OptimizationAgent implements AgentInterface
{
    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO
    {
        $findings = [];
        $recommendations = [];

        foreach ($context->tables as $table) {
            // 1. Tables without primary key
            $this->detectMissingPrimaryKey($table, $findings, $recommendations);

            // 2. Tables with high row count but no indexes
            $this->detectHighRowsNoIndex($table, $findings, $recommendations);

            // 3. Missing indexes on foreign key columns (_id suffix, not PK)
            $this->detectMissingForeignKeyIndexes($table, $findings, $recommendations);

            // 4. Duplicate indexes
            $this->detectDuplicateIndexes($table, $findings, $recommendations);

            // 5. Large tables with only primary key (no secondary indexes)
            $this->detectLargeTableNoSecondaryIndexes($table, $findings, $recommendations);

            // 6. Over-indexed tables (too many indexes for table size)
            $this->detectOverIndexed($table, $findings, $recommendations);

            // 7. Wide VARCHAR columns that could be TEXT
            $this->detectWideVarchar($table, $findings, $recommendations);

            // 8. Normalization issues (1NF / 2NF / 3NF)
            $this->detectNormalizationIssues($table, $findings, $recommendations);
        }

        // Add summary recommendations
        $this->addSummaryRecommendations($context, $findings, $recommendations);

        $score = $this->calculateScore($context, $findings);

        return new AgentResultDTO(
            agent: 'optimization',
            findings: $findings,
            recommendations: $recommendations,
            score: $score,
            metadata: [
                'total_tables' => count($context->tables),
                'total_indexes' => $this->countIndexes($context),
                'tables_without_pk' => $this->countWithoutPk($context),
                'high_rows_no_index' => $this->countHighRowsNoIndex($context),
                'source' => 'rule-based',
            ],
        );
    }

    // ─── Detection methods ─────────────────────────────────────────

    private function detectMissingPrimaryKey(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $hasPrimary = false;
        foreach ($table->columns as $column) {
            if ($column->primary) {
                $hasPrimary = true;
                break;
            }
        }

        if (! $hasPrimary) {
            $findings[] = [
                'severity' => 'high',
                'message' => "[{$table->name}] No primary key defined — every table should have a primary key for data integrity, replication, and performance",
            ];
            $recommendations[] = [
                'priority' => 'high',
                'message' => "Add a primary key to table `{$table->name}`. Use an auto-increment BIGINT or UUID column.",
            ];
        }
    }

    private function detectHighRowsNoIndex(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $indexCount = count($table->indexes);

        if ($table->rowCount > 10000 && $indexCount === 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "[{$table->name}] {$table->rowCount} rows with zero indexes — every query triggers a full table scan",
            ];
            $recommendations[] = [
                'priority' => 'high',
                'message' => "Add indexes to table `{$table->name}` on columns used in WHERE, JOIN, and ORDER BY clauses.",
            ];
        }
    }

    private function detectMissingForeignKeyIndexes(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $fkColumns = [];
        foreach ($table->columns as $column) {
            $name = $column->name;
            // Columns ending with _id that are NOT primary keys are likely FKs
            if (str_ends_with($name, '_id') && ! $column->primary && $name !== 'id') {
                $fkColumns[] = $name;
            }
        }

        if (empty($fkColumns)) {
            return;
        }

        // Collect indexed columns
        $indexedColumns = [];
        foreach ($table->indexes as $index) {
            foreach ($index->columns as $col) {
                $indexedColumns[] = $col;
            }
        }

        foreach ($fkColumns as $fkCol) {
            if (! in_array($fkCol, $indexedColumns, true)) {
                $findings[] = [
                    'severity' => 'medium',
                    'message' => "[{$table->name}] Foreign key column `{$fkCol}` has no index — causes full table scan on JOIN queries",
                ];
                $recommendations[] = [
                    'priority' => 'medium',
                    'message' => "Add index on `{$table->name}`.`{$fkCol}`: CREATE INDEX idx_{$table->name}_{$fkCol} ON {$table->name}({$fkCol});",
                ];
            }
        }
    }

    private function detectDuplicateIndexes(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $indexes = array_values($table->indexes);
        $count = count($indexes);

        if ($count < 2) {
            return;
        }

        // Compare each pair of indexes
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $colsA = $indexes[$i]->columns;
                $colsB = $indexes[$j]->columns;

                // Check if one is a prefix of the other (duplicate/redundant)
                if ($this->isPrefixOrEqual($colsA, $colsB)) {
                    $findings[] = [
                        'severity' => 'medium',
                        'message' => "[{$table->name}] Duplicate/redundant indexes: `{$indexes[$i]->name}` and `{$indexes[$j]->name}` — both cover ".implode(', ', $colsA),
                    ];
                    $recommendations[] = [
                        'priority' => 'medium',
                        'message' => "Remove redundant index `{$indexes[$j]->name}` from `{$table->name}` — index `{$indexes[$i]->name}` already covers ".implode(', ', $colsA),
                    ];
                }
            }
        }
    }

    private function detectLargeTableNoSecondaryIndexes(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $indexCount = count($table->indexes);
        $hasPrimary = false;
        foreach ($table->columns as $column) {
            if ($column->primary) {
                $hasPrimary = true;
                break;
            }
        }

        if ($table->rowCount > 50000 && $indexCount <= 1 && $hasPrimary) {
            $findings[] = [
                'severity' => 'medium',
                'message' => "[{$table->name}] Large table ({$table->rowCount} rows) has only a primary key — queries on non-PK columns will scan all rows",
            ];
            $recommendations[] = [
                'priority' => 'medium',
                'message' => "Add composite or secondary indexes to `{$table->name}` based on common query patterns (WHERE, JOIN, ORDER BY columns).",
            ];
        }
    }

    private function detectOverIndexed(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $indexCount = count($table->indexes);

        // A table with <1000 rows but >5 indexes is likely over-indexed
        if ($table->rowCount < 1000 && $indexCount > 5) {
            $findings[] = [
                'severity' => 'low',
                'message' => "[{$table->name}] Over-indexed: {$indexCount} indexes on only {$table->rowCount} rows — index maintenance overhead may exceed query benefit",
            ];
            $recommendations[] = [
                'priority' => 'low',
                'message' => "Review and remove unused indexes from `{$table->name}`. Use pt-index-usage or PERFORMANCE_SCHEMA to identify unused indexes.",
            ];
        }
    }

    private function detectWideVarchar(TableDTO $table, array &$findings, array &$recommendations): void
    {
        foreach ($table->columns as $column) {
            if (preg_match('/^varchar\((\d+)\)$/i', $column->type, $m)) {
                $length = (int) $m[1];
                if ($length > 500) {
                    $findings[] = [
                        'severity' => 'low',
                        'message' => "[{$table->name}] Column `{$column->name}` uses VARCHAR({$length}) — consider TEXT for larger strings to avoid row size limits and improve InnoDB storage efficiency",
                    ];
                    $recommendations[] = [
                        'priority' => 'low',
                        'message' => "Change `{$table->name}`.`{$column->name}` from VARCHAR({$length}) to TEXT if values exceed 500 characters.",
                    ];
                }
            }
        }
    }

    private function addSummaryRecommendations(SchemaContextDTO $context, array &$findings, array &$recommendations): void
    {
        $totalWithoutPk = $this->countWithoutPk($context);
        $totalHighRowsNoIndex = $this->countHighRowsNoIndex($context);
        $totalMissedFkIndexes = $this->countMissingFkIndexes($context);

        if ($totalWithoutPk > 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "{$totalWithoutPk} table(s) missing primary keys — affects replication, data integrity, and query performance",
            ];
        }

        if ($totalHighRowsNoIndex > 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "{$totalHighRowsNoIndex} table(s) with >10K rows have no indexes — high risk of performance degradation",
            ];
        }

        if ($totalMissedFkIndexes > 0) {
            $findings[] = [
                'severity' => 'medium',
                'message' => "{$totalMissedFkIndexes} foreign key column(s) across the schema lack indexes — JOIN performance may suffer",
            ];
        }

        // Overall recommendation
        if ($totalWithoutPk > 0 || $totalHighRowsNoIndex > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Run pt-query-digest or enable slow query log to identify the most impactful indexes to add first.',
            ];
        }
    }

    // ─── Scoring ───────────────────────────────────────────────────

    private function calculateScore(SchemaContextDTO $context, array $findings): int
    {
        $score = 100;
        $totalTables = max(count($context->tables), 1);

        foreach ($findings as $finding) {
            $score -= match ($finding['severity']) {
                'high' => 10,
                'medium' => 5,
                default => 2,
            };
        }

        // Bonus for well-indexed schemas
        $tablesWithGoodCoverage = 0;
        foreach ($context->tables as $table) {
            $indexCount = count($table->indexes);
            $fkCount = $this->countFkColumns($table);
            if ($indexCount >= 1 && ($fkCount === 0 || $this->allFkIndexed($table))) {
                $tablesWithGoodCoverage++;
            }
        }
        $coverageRatio = $tablesWithGoodCoverage / $totalTables;
        $score += (int) ($coverageRatio * 10);

        return max(0, min(100, $score));
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function countIndexes(SchemaContextDTO $context): int
    {
        $total = 0;
        foreach ($context->tables as $table) {
            $total += count($table->indexes);
        }

        return $total;
    }

    private function countWithoutPk(SchemaContextDTO $context): int
    {
        $count = 0;
        foreach ($context->tables as $table) {
            $hasPk = false;
            foreach ($table->columns as $col) {
                if ($col->primary) {
                    $hasPk = true;
                    break;
                }
            }
            if (! $hasPk) {
                $count++;
            }
        }

        return $count;
    }

    private function countHighRowsNoIndex(SchemaContextDTO $context): int
    {
        $count = 0;
        foreach ($context->tables as $table) {
            if ($table->rowCount > 10000 && count($table->indexes) === 0) {
                $count++;
            }
        }

        return $count;
    }

    private function countMissingFkIndexes(SchemaContextDTO $context): int
    {
        $count = 0;
        foreach ($context->tables as $table) {
            $fkCols = $this->getFkColumns($table);
            $indexedCols = $this->getIndexedColumns($table);
            foreach ($fkCols as $fkCol) {
                if (! in_array($fkCol, $indexedCols, true)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countFkColumns(TableDTO $table): int
    {
        return count($this->getFkColumns($table));
    }

    /**
     * @return string[]
     */
    private function getFkColumns(TableDTO $table): array
    {
        $fkCols = [];
        foreach ($table->columns as $column) {
            if (str_ends_with($column->name, '_id') && ! $column->primary && $column->name !== 'id') {
                $fkCols[] = $column->name;
            }
        }

        return $fkCols;
    }

    /**
     * @return string[]
     */
    private function getIndexedColumns(TableDTO $table): array
    {
        $cols = [];
        foreach ($table->indexes as $index) {
            foreach ($index->columns as $col) {
                $cols[] = $col;
            }
        }

        return $cols;
    }

    private function allFkIndexed(TableDTO $table): bool
    {
        $fkCols = $this->getFkColumns($table);
        if (empty($fkCols)) {
            return true;
        }
        $indexedCols = $this->getIndexedColumns($table);
        foreach ($fkCols as $fkCol) {
            if (! in_array($fkCol, $indexedCols, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if columns array A is a prefix of or equal to columns array B.
     */
    private function isPrefixOrEqual(array $a, array $b): bool
    {
        if (count($a) > count($b)) {
            // Swap so A is always the shorter or equal
            return $this->isPrefixOrEqual($b, $a);
        }

        foreach ($a as $i => $col) {
            if (! isset($b[$i]) || $b[$i] !== $col) {
                return false;
            }
        }

        return true;
    }

    // ─── Normalization Checker (1NF / 2NF / 3NF) ────────────────────

    private function detectNormalizationIssues(TableDTO $table, array &$findings, array &$recommendations): void
    {
        $columns = $table->columns;
        $colNames = array_map(fn (ColumnDTO $c) => $c->name, $columns);

        // 1NF: Detect repeating groups (col_1, col_2, col_3 patterns)
        $repeatingGroups = $this->findRepeatingGroups($colNames);
        if (! empty($repeatingGroups)) {
            foreach ($repeatingGroups as $group) {
                $findings[] = [
                    'severity' => 'medium',
                    'message' => "[{$table->name}] 1NF violation: repeating group '{$group['prefix']}' (columns: {$group['columns']}) — consider a separate related table",
                ];
                $recommendations[] = [
                    'priority' => 'medium',
                    'message' => "Extract {$group['prefix']} columns from `{$table->name}` into a separate table with a foreign key back to `{$table->name}`.",
                ];
            }
        }

        // 1NF: Detect JSON/array columns that should be separate tables
        foreach ($columns as $col) {
            if (in_array(strtoupper($col->type), ['JSON', 'ARRAY'], true)) {
                $findings[] = [
                    'severity' => 'info',
                    'message' => "[{$table->name}] Column `{$col->name}` is type {$col->type} — consider normalizing into a related table for queryability",
                ];
                $recommendations[] = [
                    'priority' => 'low',
                    'message' => "If `{$table->name}`.`{$col->name}` contains structured data, extract it into a child table with a foreign key.",
                ];
            }
        }

        // 2NF / 3NF: Detect denormalized repeated prefixes
        $repeatedPrefixes = $this->findDenormalizedPrefixes($colNames);
        if (! empty($repeatedPrefixes)) {
            foreach ($repeatedPrefixes as $item) {
                $findings[] = [
                    'severity' => 'low',
                    'message' => "[{$table->name}] Possible denormalization: columns with '{$item['prefix']}' prefix ({$item['columns']}) might belong to a related entity",
                ];
                $recommendations[] = [
                    'priority' => 'low',
                    'message' => "Review if {$item['prefix']} columns in `{$table->name}` should be in a separate table.",
                ];
            }
        }
    }

    /**
     * Find repeating groups like phone_1, phone_2, phone_3 or col1, col2, col3.
     */
    private function findRepeatingGroups(array $colNames): array
    {
        $groups = [];
        $pattern = '/^(.+?)[_\s]?(\d+)$/';

        $matches = [];
        foreach ($colNames as $col) {
            if (preg_match($pattern, $col, $m)) {
                $prefix = strtolower($m[1]);
                $matches[$prefix][] = $col;
            }
        }

        foreach ($matches as $prefix => $cols) {
            if (count($cols) >= 2) {
                $groups[] = [
                    'prefix' => $prefix,
                    'columns' => implode(', ', $cols),
                ];
            }
        }

        // Sort by repetition count (most egregious first)
        usort($groups, fn ($a, $b) => count(explode(', ', $b['columns'])) <=> count(explode(', ', $a['columns'])));

        return $groups;
    }

    /**
     * Find denormalized prefixes like address_street, address_city, address_zip.
     */
    private function findDenormalizedPrefixes(array $colNames): array
    {
        $groups = [];
        $prefixCount = [];

        foreach ($colNames as $col) {
            $parts = explode('_', $col);
            if (count($parts) >= 2) {
                $prefix = $parts[0];
                if (! isset($prefixCount[$prefix])) {
                    $prefixCount[$prefix] = [];
                }
                $prefixCount[$prefix][] = $col;
            }
        }

        foreach ($prefixCount as $prefix => $cols) {
            // Minimum 3 columns with same prefix to be suspicious
            if (count($cols) >= 3 && ! in_array($prefix, ['created', 'updated', 'deleted'], true)) {
                $groups[] = [
                    'prefix' => $prefix,
                    'columns' => implode(', ', $cols),
                ];
            }
        }

        return $groups;
    }
}
