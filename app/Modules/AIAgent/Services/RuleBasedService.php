<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Services;

class RuleBasedService
{
    /**
     * Process a task using deterministic rule-based analysis.
     *
     * This is the DEFAULT and PREFERRED path for schema analysis,
     * security audits, and optimization suggestions. It incurs zero
     * external API cost and returns instantly.
     */
    public function process(string $task, string $prompt): string
    {
        // Extract schema context from the prompt JSON
        $schema = $this->extractSchema($prompt);

        return match ($task) {
            'schema_analysis' => $this->analyzeSchema($schema),
            'domain_clustering' => $this->clusterDomains($schema),
            'optimization' => $this->optimizationSuggestions($schema),
            'security_analysis' => $this->analyzeSecurity($schema),
            'documentation' => $this->generateDocs($schema),
            'chat' => $this->chatFallback(),
            default => json_encode([
                'findings' => [['severity' => 'info', 'message' => 'Rule-based analysis complete.']],
                'recommendations' => [],
                'score' => 0,
            ]),
        };
    }

    /**
     * Extract structured schema data from the JSON-encoded prompt.
     */
    private function extractSchema(string $prompt): array
    {
        $data = json_decode($prompt, true);

        if (!$data || !is_array($data)) {
            // Try to extract from raw prompt text
            if (preg_match('/\{.*"tables".*\}/s', $prompt, $m)) {
                $data = json_decode($m[0], true);
            }
        }

        // The prompt is typically an array of messages; find the user content
        if (is_array($data) && isset($data[0]['content'])) {
            // Extract schema from message content
            foreach ($data as $msg) {
                $content = $msg['content'] ?? '';
                if (preg_match('/"tables"\s*:\s*\[/s', $content)) {
                    $parsed = json_decode($content, true);
                    if ($parsed && is_array($parsed)) {
                        return $parsed;
                    }
                    // Try to extract just the schema object
                    if (preg_match('/\{[\s\S]*"tables"[\s\S]*\}/s', $content, $schemaMatch)) {
                        $parsed = json_decode($schemaMatch[0], true);
                        if ($parsed) {
                            return $parsed;
                        }
                    }
                }
            }
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Analyze schema structure deterministically.
     */
    private function analyzeSchema(array $schema): string
    {
        $tables = $this->getTables($schema);
        $findings = [];
        $recommendations = [];
        $clusters = [];

        $totalTables = count($tables);
        $tablesWithIndexes = 0;
        $tablesWithoutPk = 0;
        $highRowNoIndex = 0;
        $totalSizeMb = 0;
        $totalRows = 0;

        foreach ($tables as $table) {
            $name = $table['name'] ?? 'unknown';
            $columns = $table['columns'] ?? [];
            $indexes = $table['indexes'] ?? [];
            $rowCount = $table['row_count'] ?? $table['rowCount'] ?? 0;
            $sizeMb = $table['size_mb'] ?? $table['sizeMb'] ?? 0;

            $totalRows += $rowCount;
            $totalSizeMb += $sizeMb;

            if (count($indexes) > 0) {
                $tablesWithIndexes++;
            }

            $hasPk = false;
            foreach ($columns as $col) {
                if (($col['primary'] ?? false) || ($col['autoIncrement'] ?? false)) {
                    $hasPk = true;
                    break;
                }
            }

            if (!$hasPk) {
                $tablesWithoutPk++;
                $findings[] = [
                    'severity' => 'high',
                    'message' => "[{$name}] No primary key — may cause replication and performance issues",
                ];
            }

            if ($rowCount > 10000 && count($indexes) === 0) {
                $highRowNoIndex++;
                $findings[] = [
                    'severity' => 'high',
                    'message' => "[{$name}] {$rowCount} rows but no indexes — full table scan on every query",
                ];
            }

            if ($sizeMb > 500) {
                $findings[] = [
                    'severity' => 'medium',
                    'message' => "[{$name}] Size is {$sizeMb}MB — consider partitioning or archiving",
                ];
            }

            // Domain clustering
            $domain = $this->inferDomainFromName($name, $columns);
            $clusters[$domain][] = $name;
        }

        $indexCoverage = $totalTables > 0 ? round(($tablesWithIndexes / $totalTables) * 100, 1) : 100;
        $scanRisk = $totalTables > 0 ? round((($totalTables - $tablesWithIndexes) / $totalTables) * 100, 1) : 0;

        // Aggregate findings
        if ($tablesWithoutPk > 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "{$tablesWithoutPk} table(s) missing primary keys",
            ];
        }

        if ($highRowNoIndex > 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "{$highRowNoIndex} table(s) with >10K rows have no indexes",
            ];
        }

        if ($totalSizeMb > 1000) {
            $findings[] = [
                'severity' => 'medium',
                'message' => "Database size is {$totalSizeMb}MB — consider archiving or partitioning",
            ];
        }

        // Recommendations
        if ($indexCoverage < 80) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => "Improve index coverage (currently {$indexCoverage}%). Add indexes to tables used in WHERE/JOIN/ORDER BY.",
            ];
        } else {
            $recommendations[] = [
                'priority' => 'low',
                'message' => 'Index coverage is good. Continue monitoring for slow queries.',
            ];
        }

        if ($tablesWithoutPk > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Add primary keys to all tables. Use auto-increment BIGINT or UUID.',
            ];
        }

        $recommendations[] = [
            'priority' => 'medium',
            'message' => 'Set up performance_schema to track slow queries and table scan metrics.',
        ];

        // Build domain clusters
        $clusterList = [];
        foreach ($clusters as $name => $tablesInCluster) {
            $clusterList[] = [
                'name' => $name,
                'tables' => $tablesInCluster,
                'color' => $this->domainColor($name),
            ];
        }

        // Health score calculation (same 4-dimension algorithm as MonitoringAgent)
        $performanceDim = $indexCoverage;
        $structureDim = $totalTables > 0
            ? ((1 - ($tablesWithoutPk / max($totalTables, 1))) * 100)
            : 100;
        $indexDim = 100 - $scanRisk;
        $securityDim = $tablesWithoutPk === 0 ? 90 : max(0, 90 - ($tablesWithoutPk * 10));

        $healthScore = (int) min(100,
            ($performanceDim * 0.35) +
            ($structureDim * 0.30) +
            ($indexDim * 0.20) +
            ($securityDim * 0.15)
        );

        return json_encode([
            'findings' => $findings,
            'recommendations' => $recommendations,
            'score' => $healthScore,
            'clusters' => $clusterList,
            'metadata' => [
                'total_tables' => $totalTables,
                'total_rows' => $totalRows,
                'total_size_mb' => round($totalSizeMb, 2),
                'index_coverage' => $indexCoverage,
                'tables_without_pk' => $tablesWithoutPk,
                'high_row_no_index' => $highRowNoIndex,
                'source' => 'rule-based',
            ],
        ]);
    }

    /**
     * Security analysis using deterministic rules.
     */
    private function analyzeSecurity(array $schema): string
    {
        $tables = $this->getTables($schema);
        $findings = [];
        $recommendations = [];

        foreach ($tables as $table) {
            $name = $table['name'] ?? 'unknown';
            $columns = $table['columns'] ?? [];

            $hasPassword = false;
            $hasEmail = false;
            $hasToken = false;
            $hasSalt = false;
            $sensitiveCols = [];

            foreach ($columns as $col) {
                $colName = strtolower($col['name'] ?? '');
                if (in_array($colName, ['password', 'passwd', 'pwd'])) {
                    $hasPassword = true;
                    $sensitiveCols[] = $col['name'];
                }
                if (in_array($colName, ['email', 'e_mail'])) {
                    $hasEmail = true;
                }
                if (str_contains($colName, 'token') || str_contains($colName, 'secret')) {
                    $hasToken = true;
                    $sensitiveCols[] = $col['name'];
                }
                if (str_contains($colName, 'salt')) {
                    $hasSalt = true;
                }
            }

            if ($hasPassword && !$hasSalt) {
                $findings[] = [
                    'severity' => 'high',
                    'message' => "[{$name}] Password column detected without salt column — hashing may be weak",
                ];
            }

            if ($hasToken) {
                $findings[] = [
                    'severity' => 'medium',
                    'message' => "[{$name}] Token/secret columns present — ensure encrypted at rest",
                ];
            }

            // Check for soft deletes
            $hasDeletedAt = false;
            foreach ($columns as $col) {
                if (strtolower($col['name'] ?? '') === 'deleted_at') {
                    $hasDeletedAt = true;
                    break;
                }
            }

            if (!$hasDeletedAt && $this->isDataTable($name)) {
                $recommendations[] = [
                    'priority' => 'low',
                    'message' => "Consider soft-delete (deleted_at) for table `{$name}` to prevent accidental data loss.",
                ];
            }
        }

        // Overall recommendations
        $recommendations[] = [
            'priority' => 'high',
            'message' => 'Review all password columns use bcrypt/Argon2 with per-user salt.',
        ];
        $recommendations[] = [
            'priority' => 'medium',
            'message' => 'Ensure PII columns (email, phone, address) are encrypted at rest.',
        ];
        $recommendations[] = [
            'priority' => 'medium',
            'message' => 'Verify connection uses SSL/TLS for data in transit.',
        ];

        $score = max(0, 100 - (count($findings) * 15));

        return json_encode([
            'findings' => $findings,
            'recommendations' => $recommendations,
            'score' => $score,
            'report' => [
                'total_tables_scanned' => count($tables),
                'sensitive_tables' => count(array_filter($tables, fn ($t) => $this->hasSensitiveData($t))),
            ],
            'metadata' => ['source' => 'rule-based'],
        ]);
    }

    /**
     * Optimization suggestions based on schema structure.
     */
    private function optimizationSuggestions(array $schema): string
    {
        $tables = $this->getTables($schema);
        $recommendations = [];

        foreach ($tables as $table) {
            $name = $table['name'] ?? 'unknown';
            $columns = $table['columns'] ?? [];
            $indexes = $table['indexes'] ?? [];
            $rowCount = $table['row_count'] ?? $table['rowCount'] ?? 0;

            // Missing indexes on foreign key columns
            $fkColumns = [];
            $indexedColumns = [];

            foreach ($indexes as $idx) {
                $idxCols = $idx['columns'] ?? [];
                foreach ($idxCols as $col) {
                    $indexedColumns[] = strtolower($col);
                }
            }

            foreach ($columns as $col) {
                $colName = strtolower($col['name'] ?? '');
                if (str_ends_with($colName, '_id') && $colName !== 'id') {
                    $fkColumns[] = $col['name'];
                    if (!in_array($colName, $indexedColumns)) {
                        $recommendations[] = [
                            'type' => 'index',
                            'priority' => 'high',
                            'table' => $name,
                            'message' => "Add index on `{$col['name']}` — foreign key column without index causes full table scan on JOIN",
                            'sql' => "CREATE INDEX idx_{$name}_{$col['name']} ON {$name}({$col['name']});",
                        ];
                    }
                }
            }

            // Large tables without composite indexes
            if ($rowCount > 50000 && count($indexes) <= 1) {
                $recommendations[] = [
                    'type' => 'index',
                    'priority' => 'medium',
                    'table' => $name,
                    'message' => "Large table ({$rowCount} rows) has only {$indexes} index(es) — consider composite indexes for common query patterns.",
                    'sql' => null,
                ];
            }

            // Check for TEXT/BLOB columns in frequent query tables
            foreach ($columns as $col) {
                $type = strtolower($col['type'] ?? '');
                if ((str_contains($type, 'text') || str_contains($type, 'blob')) && $rowCount > 10000) {
                    $recommendations[] = [
                        'type' => 'storage',
                        'priority' => 'low',
                        'table' => $name,
                        'message' => "Consider storing large TEXT/BLOB columns in separate table to reduce row size and improve cache efficiency.",
                        'sql' => null,
                    ];
                    break;
                }
            }
        }

        return json_encode([
            'recommendations' => $recommendations,
            'total_suggestions' => count($recommendations),
            'metadata' => ['source' => 'rule-based'],
        ]);
    }

    /**
     * Domain clustering based on table names and columns.
     */
    private function clusterDomains(array $schema): string
    {
        $tables = $this->getTables($schema);
        $clusters = [];

        foreach ($tables as $table) {
            $name = $table['name'] ?? 'unknown';
            $domain = $this->inferDomainFromName($name, $table['columns'] ?? []);
            $clusters[$domain][] = $name;
        }

        $clusterList = [];
        foreach ($clusters as $name => $tablesInCluster) {
            $clusterList[] = [
                'name' => $name,
                'tables' => $tablesInCluster,
                'color' => $this->domainColor($name),
                'description' => ucfirst($name) . ' domain tables',
            ];
        }

        return json_encode([
            'clusters' => $clusterList,
            'total_clusters' => count($clusterList),
            'metadata' => ['source' => 'rule-based'],
        ]);
    }

    /**
     * Generate documentation structure from schema.
     */
    private function generateDocs(array $schema): string
    {
        $tables = $this->getTables($schema);
        $tableDocs = [];

        foreach ($tables as $table) {
            $name = $table['name'] ?? 'unknown';
            $columns = $table['columns'] ?? [];
            $columnDocs = [];

            foreach ($columns as $col) {
                $columnDocs[] = [
                    'name' => $col['name'] ?? '',
                    'type' => $col['type'] ?? '',
                    'description' => $this->inferColumnDesc($col['name'] ?? '', $col['type'] ?? ''),
                ];
            }

            $tableDocs[] = [
                'name' => $name,
                'description' => $this->inferTableDesc($table),
                'domain' => $this->inferDomainFromName($name, $columns),
                'columns' => $columnDocs,
            ];
        }

        return json_encode([
            'tables' => $tableDocs,
            'total_tables' => count($tableDocs),
            'metadata' => ['source' => 'rule-based'],
        ]);
    }

    /**
     * Fallback for chat when no AI provider is configured.
     */
    private function chatFallback(): string
    {
        return json_encode([
            'role' => 'assistant',
            'content' => 'AetherDB AI siap membantu. Saat ini mode analisis deterministik aktif (zero API cost). Untuk jawaban lebih kreatif, aktifkan AI provider di pengaturan.',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function getTables(array $schema): array
    {
        if (isset($schema['tables']) && is_array($schema['tables'])) {
            return $schema['tables'];
        }

        // Try to find tables array nested in the structure
        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value) && isset($value[0]['name'])) {
                    return $value;
                }
                if (isset($value['tables'])) {
                    return $value['tables'];
                }
            }
        }

        return [];
    }

    private function inferDomainFromName(string $tableName, array $columns): string
    {
        $name = strtolower($tableName);

        $patterns = [
            'auth|user|role|permission|session|login|oauth|passkey' => 'auth',
            'order|invoice|payment|cart|checkout|transaction|billing|subscription' => 'commerce',
            'product|inventory|stock|warehouse|supplier|category|catalog|item' => 'catalog',
            'account|ledger|balance|budget|expense|revenue|finance|wallet' => 'finance',
            'post|article|comment|page|media|file|content|blog|news' => 'content',
            'customer|contact|lead|deal|opportunity|account|crm|client' => 'crm',
            'log|audit|event|history|trace|monitor|activity' => 'audit',
            'config|setting|preference|meta|flag|feature|option' => 'config',
            'notification|message|email|sms|alert|inbox' => 'notification',
            'team|member|group|organization|workspace' => 'collaboration',
            'ai_|analysis|insight|recommendation|report' => 'ai',
        ];

        foreach ($patterns as $pattern => $domain) {
            if (preg_match("/{$pattern}/i", $name)) {
                return $domain;
            }
        }

        // Column-based fallback
        foreach ($columns as $col) {
            $colName = strtolower($col['name'] ?? '');
            if (in_array($colName, ['price', 'amount', 'total', 'subtotal', 'cost'])) {
                return 'commerce';
            }
            if (in_array($colName, ['email', 'password', 'remember_token', 'api_token'])) {
                return 'auth';
            }
        }

        return 'general';
    }

    private function domainColor(string $domain): string
    {
        return match ($domain) {
            'auth' => '#3b82f6',
            'commerce' => '#10b981',
            'catalog' => '#8b5cf6',
            'finance' => '#f59e0b',
            'content' => '#ec4899',
            'crm' => '#06b6d4',
            'audit' => '#6b7280',
            'config' => '#84cc16',
            'notification' => '#f97316',
            'collaboration' => '#6366f1',
            'ai' => '#a855f7',
            default => '#9ca3af',
        };
    }

    private function isDataTable(string $name): string
    {
        $name = strtolower($name);
        $exclude = ['session', 'cache', 'log', 'migration', 'job', 'queue', 'failed', 'password'];
        foreach ($exclude as $ex) {
            if (str_contains($name, $ex)) {
                return false;
            }
        }
        return true;
    }

    private function hasSensitiveData(array $table): bool
    {
        $columns = $table['columns'] ?? [];
        foreach ($columns as $col) {
            $name = strtolower($col['name'] ?? '');
            if (in_array($name, ['password', 'email', 'token', 'secret', 'ssn', 'phone'])) {
                return true;
            }
        }
        return false;
    }

    private function inferColumnDesc(string $name, string $type): string
    {
        $name = strtolower($name);
        $map = [
            'id' => 'Unique identifier',
            'uuid' => 'Universally unique identifier',
            'created_at' => 'Timestamp when record was created',
            'updated_at' => 'Timestamp when record was last updated',
            'deleted_at' => 'Soft-delete timestamp',
            'email' => 'Email address',
            'password' => 'Hashed password',
            'name' => 'Name or title',
            'slug' => 'URL-friendly identifier',
            'status' => 'Current status',
            'type' => 'Type classification',
            'is_active' => 'Active flag',
            'sort_order' => 'Sort position',
            'parent_id' => 'Parent record reference',
        ];

        foreach ($map as $pattern => $desc) {
            if (str_contains($name, $pattern)) {
                return $desc;
            }
        }

        $typeLower = strtolower($type);
        if (str_contains($typeLower, 'int')) {
            return 'Numeric value';
        }
        if (str_contains($typeLower, 'varchar') || str_contains($typeLower, 'text')) {
            return 'Text value';
        }
        if (str_contains($typeLower, 'decimal') || str_contains($typeLower, 'float')) {
            return 'Decimal value';
        }
        if (str_contains($typeLower, 'bool') || str_contains($typeLower, 'tinyint')) {
            return 'Boolean flag';
        }
        if (str_contains($typeLower, 'date') || str_contains($typeLower, 'time')) {
            return 'Date/timestamp';
        }

        return ucfirst(str_replace('_', ' ', $name));
    }

    private function inferTableDesc(array $table): string
    {
        $name = $table['name'] ?? 'unknown';
        $columns = $table['columns'] ?? [];
        $rowCount = $table['row_count'] ?? $table['rowCount'] ?? 0;

        $hasTimestamps = false;
        foreach ($columns as $col) {
            if (in_array(strtolower($col['name'] ?? ''), ['created_at', 'updated_at'])) {
                $hasTimestamps = true;
                break;
            }
        }

        $parts = ["Table `{$name}`"];
        $parts[] = count($columns) . ' columns';
        if ($hasTimestamps) {
            $parts[] = 'with timestamps';
        }
        $parts[] = '~' . $rowCount . ' rows';

        return implode(', ', $parts) . '.';
    }
}
