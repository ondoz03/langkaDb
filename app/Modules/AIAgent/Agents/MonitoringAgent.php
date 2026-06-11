<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\Agents;

use App\Modules\AIAgent\Contracts\AgentInterface;
use App\Modules\AIAgent\DTOs\AgentResultDTO;
use App\Modules\AIAgent\DTOs\AnomalyDTO;
use App\Modules\AIAgent\DTOs\MonitorReportDTO;
use App\Modules\AIAgent\DTOs\PerformanceMetricDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

class MonitoringAgent implements AgentInterface
{
    private ?MonitorReportDTO $lastReport = null;

    /**
     * Analyze schema context and produce monitoring findings.
     *
     * Evaluates table sizes, index coverage, and growth patterns
     * to generate performance-oriented insights.
     */
    public function analyze(SchemaContextDTO $context, ?string $apiKey = null, string $provider = 'rule'): AgentResultDTO
    {
        $findings = [];
        $recommendations = [];
        $tableAnalyses = [];
        $anomalies = [];

        foreach ($context->tables as $table) {
            $analysis = $this->analyzeTable($table);
            $tableAnalyses[] = $analysis;

            if (! empty($analysis['warnings'])) {
                foreach ($analysis['warnings'] as $warning) {
                    $findings[] = [
                        'severity' => $warning['severity'],
                        'message' => "[{$table->name}] {$warning['message']}",
                    ];
                }
            }
        }

        // Compute aggregate metrics
        $totalTables = count($context->tables);
        $tablesWithIndexes = count(array_filter($context->tables, fn (TableDTO $t) => count($t->indexes) > 0));
        $tablesWithHighRows = count(array_filter($context->tables, fn (TableDTO $t) => $t->rowCount > 10000));
        $highRowsWithoutIndex = count(array_filter($context->tables, fn (TableDTO $t) => $t->rowCount > 10000 && count($t->indexes) === 0));

        $indexCoverageRatio = $totalTables > 0 ? round(($tablesWithIndexes / $totalTables) * 100, 1) : 100;
        $tableScanRatio = $totalTables > 0 ? round((($totalTables - $tablesWithIndexes) / $totalTables) * 100, 1) : 0;
        $totalSizeMb = array_sum(array_map(fn (TableDTO $t) => $t->sizeMb, $context->tables));
        $totalRows = array_sum(array_map(fn (TableDTO $t) => $t->rowCount, $context->tables));

        // Health scoring (simplified from PLAN.md section 7.5)
        // Each dimension scored 0-100, then weighted
        $performanceDimension = $indexCoverageRatio; // 0-100: index coverage
        $structureDimension = $totalTables > 0
            ? ((1 - ($highRowsWithoutIndex / max($totalTables, 1))) * 100)
            : 100;
        $indexDimension = 100 - $tableScanRatio; // 0-100: lower scan ratio = better
        $securityDimension = 85; // placeholder — actual security audit in SecurityAgent

        $healthScore = (int) min(100,
            ($performanceDimension * 0.35) +
            ($structureDimension * 0.30) +
            ($indexDimension * 0.20) +
            ($securityDimension * 0.15)
        );
        $healthGrade = $this->computeGrade($healthScore);

        // Generate findings from aggregate analysis
        if ($tableScanRatio > 30) {
            $tablesWithoutIndexes = $totalTables - $tablesWithIndexes;
            $findings[] = [
                'severity' => 'high',
                'message' => "High table scan ratio ({$tableScanRatio}%): {$tablesWithoutIndexes} tables lack indexes",
            ];
        }

        if ($highRowsWithoutIndex > 0) {
            $findings[] = [
                'severity' => 'high',
                'message' => "{$highRowsWithoutIndex} table(s) with >10K rows have no indexes — risk of full table scans",
            ];
        }

        if ($totalSizeMb > 1000) {
            $findings[] = [
                'severity' => 'medium',
                'message' => "Database size is {$totalSizeMb}MB — consider archiving or partitioning large tables",
            ];
        }

        if ($indexCoverageRatio >= 80) {
            $recommendations[] = [
                'priority' => 'low',
                'message' => 'Index coverage is good. Continue monitoring for slow queries.',
            ];
        } else {
            $recommendations[] = [
                'priority' => 'high',
                'message' => "Improve index coverage (currently {$indexCoverageRatio}%). Add indexes to tables used in WHERE/JOIN/ORDER BY.",
            ];
        }

        $recommendations[] = [
            'priority' => 'medium',
            'message' => 'Set up performance_schema to track slow queries and table scan metrics.',
        ];

        // Build anomalies from findings
        foreach ($findings as $finding) {
            if ($finding['severity'] === 'high') {
                $anomalies[] = new AnomalyDTO(
                    type: 'performance_risk',
                    severity: $finding['severity'],
                    message: $finding['message'],
                );
            }
        }

        // Build and store the report for getHealthSummary()
        $this->lastReport = new MonitorReportDTO(
            agent: 'monitoring',
            metrics: [
                new PerformanceMetricDTO(
                    qps: 0,
                    latencyMs: 0,
                    connectionCount: 0,
                    measuredAt: date('c'),
                ),
            ],
            anomalies: $anomalies,
            healthScore: $healthScore,
            healthGrade: $healthGrade,
            tables: $tableAnalyses,
            summary: [
                'total_tables' => $totalTables,
                'total_size_mb' => round($totalSizeMb, 2),
                'total_rows' => $totalRows,
                'index_coverage_ratio' => $indexCoverageRatio,
                'table_scan_ratio' => $tableScanRatio,
                'high_rows_no_index' => $highRowsWithoutIndex,
            ],
        );

        return new AgentResultDTO(
            agent: 'monitoring',
            findings: $findings,
            recommendations: $recommendations,
            score: $healthScore,
            metadata: [
                'health_grade' => $healthGrade,
                'index_coverage_ratio' => $indexCoverageRatio,
                'table_scan_ratio' => $tableScanRatio,
                'total_tables' => $totalTables,
                'total_size_mb' => round($totalSizeMb, 2),
                'table_analyses' => $tableAnalyses,
            ],
        );
    }

    /**
     * Analyze performance from raw metric snapshots.
     *
     * @param  array  $metrics  Array of metric snapshots, each with qps, latency_ms, connection_count, measured_at
     */
    public function analyzePerformance(array $metrics): MonitorReportDTO
    {
        $metricDTOs = [];
        $anomalies = [];

        foreach ($metrics as $entry) {
            $dto = $entry instanceof PerformanceMetricDTO
                ? $entry
                : PerformanceMetricDTO::fromArray($entry);
            $metricDTOs[] = $dto;
        }

        if (count($metricDTOs) < 2) {
            $anomalies[] = new AnomalyDTO(
                type: 'insufficient_data',
                severity: 'low',
                message: 'Need at least 2 metric snapshots for trend analysis.',
            );

            return new MonitorReportDTO(
                agent: 'monitoring',
                metrics: $metricDTOs,
                anomalies: $anomalies,
                healthScore: 50,
                healthGrade: 'N/A',
                tables: [],
                summary: ['status' => 'insufficient_data'],
            );
        }

        // Analyze latency trends
        $latencies = array_map(fn (PerformanceMetricDTO $m) => $m->latencyMs, $metricDTOs);
        $avgLatency = array_sum($latencies) / count($latencies);
        $maxLatency = max($latencies);

        // Analyze QPS trends
        $qpsValues = array_map(fn (PerformanceMetricDTO $m) => $m->qps, $metricDTOs);
        $avgQps = array_sum($qpsValues) / count($qpsValues);
        $maxQps = max($qpsValues);

        // Analyze connection trends
        $connections = array_map(fn (PerformanceMetricDTO $m) => $m->connectionCount, $metricDTOs);
        $avgConnections = array_sum($connections) / count($connections);
        $maxConnections = max($connections);

        // Detect anomalies
        if ($maxLatency > 1000) {
            $anomalies[] = new AnomalyDTO(
                type: 'high_latency',
                severity: 'critical',
                message: "Peak latency {$maxLatency}ms exceeds threshold (1000ms). Investigate slow queries.",
                context: ['peak_latency_ms' => $maxLatency, 'avg_latency_ms' => round($avgLatency, 2)],
            );
        } elseif ($avgLatency > 500) {
            $anomalies[] = new AnomalyDTO(
                type: 'high_latency',
                severity: 'high',
                message: "Average latency {$avgLatency}ms above 500ms threshold.",
                context: ['avg_latency_ms' => round($avgLatency, 2)],
            );
        }

        if ($maxConnections > 500) {
            $anomalies[] = new AnomalyDTO(
                type: 'connection_spike',
                severity: 'high',
                message: "Connection count peaked at {$maxConnections}. Review connection pooling.",
                context: ['peak_connections' => $maxConnections, 'avg_connections' => round($avgConnections, 1)],
            );
        }

        // Compute health score based on performance metrics
        $latencyScore = max(0, 100 - (int) ($avgLatency / 10));
        $qpsScore = $avgQps > 0 ? min(100, (int) ($avgQps * 10)) : 50;
        $connectionScore = max(0, 100 - (int) ($avgConnections / 10));

        $healthScore = (int) (($latencyScore * 0.4) + ($qpsScore * 0.3) + ($connectionScore * 0.3));

        $summary = [
            'avg_qps' => round($avgQps, 2),
            'peak_qps' => round($maxQps, 2),
            'avg_latency_ms' => round($avgLatency, 2),
            'peak_latency_ms' => round($maxLatency, 2),
            'avg_connections' => round($avgConnections, 1),
            'peak_connections' => $maxConnections,
        ];

        $report = new MonitorReportDTO(
            agent: 'monitoring',
            metrics: $metricDTOs,
            anomalies: $anomalies,
            healthScore: $healthScore,
            healthGrade: $this->computeGrade($healthScore),
            tables: [],
            summary: $summary,
        );

        $this->lastReport = $report;

        return $report;
    }

    /**
     * Analyze table growth from schema context.
     *
     * @return array<int, array{name: string, size_mb: float, row_count: int, index_count: int, scan_risk: string}>
     */
    public function trackGrowth(SchemaContextDTO $context): array
    {
        $growthData = [];

        foreach ($context->tables as $table) {
            $indexCount = count($table->indexes);
            $scanRisk = $this->computeScanRisk($table, $indexCount);

            $growthData[] = [
                'name' => $table->name,
                'size_mb' => round($table->sizeMb, 4),
                'row_count' => $table->rowCount,
                'index_count' => $indexCount,
                'scan_risk' => $scanRisk,
            ];
        }

        // Sort by size descending (largest first)
        usort($growthData, fn (array $a, array $b) => $b['size_mb'] <=> $a['size_mb']);

        return $growthData;
    }

    /**
     * Detect anomalies from historical metric data.
     *
     * @param  array  $history  Array of snapshots, each containing qps, latency_ms, connection_count
     * @return array<AnomalyDTO>
     */
    public function detectAnomalies(array $history): array
    {
        $anomalies = [];

        if (count($history) < 3) {
            $anomalies[] = new AnomalyDTO(
                type: 'insufficient_history',
                severity: 'low',
                message: 'Need at least 3 data points for anomaly detection.',
            );

            return $anomalies;
        }

        // Extract metric series
        $latencies = array_map(fn ($h) => (float) ($h['latency_ms'] ?? 0), $history);
        $qpsValues = array_map(fn ($h) => (float) ($h['qps'] ?? 0), $history);
        $connections = array_map(fn ($h) => (int) ($h['connection_count'] ?? 0), $history);

        // Detect latency spike (> 2x standard deviation)
        $latencyMean = array_sum($latencies) / count($latencies);
        $latencyVariance = array_sum(array_map(fn ($v) => ($v - $latencyMean) ** 2, $latencies)) / count($latencies);
        $latencyStdDev = sqrt($latencyVariance);

        foreach ($latencies as $i => $latency) {
            if ($latencyStdDev > 0 && $latency > ($latencyMean + 2 * $latencyStdDev)) {
                $anomalies[] = new AnomalyDTO(
                    type: 'latency_spike',
                    severity: 'high',
                    message: "Latency spike detected at index {$i}: {$latency}ms (mean: ".round($latencyMean, 2).'ms)',
                    context: [
                        'index' => $i,
                        'value' => $latency,
                        'mean' => round($latencyMean, 2),
                        'stddev' => round($latencyStdDev, 2),
                    ],
                );
            }
        }

        // Detect connection saturation (rapid growth in connections)
        for ($i = 1; $i < count($connections); $i++) {
            $growth = $connections[$i] - $connections[$i - 1];
            if ($growth > 100) {
                $anomalies[] = new AnomalyDTO(
                    type: 'connection_growth',
                    severity: 'medium',
                    message: "Connection count jumped by {$growth} between snapshot ".($i - 1)." and {$i}.",
                    context: [
                        'from' => $connections[$i - 1],
                        'to' => $connections[$i],
                        'growth' => $growth,
                    ],
                );
            }
        }

        // Detect QPS drops (potential service degradation)
        for ($i = 1; $i < count($qpsValues); $i++) {
            if ($qpsValues[$i - 1] > 0) {
                $dropRatio = ($qpsValues[$i - 1] - $qpsValues[$i]) / $qpsValues[$i - 1];
                if ($dropRatio > 0.5) {
                    $anomalies[] = new AnomalyDTO(
                        type: 'qps_drop',
                        severity: 'high',
                        message: 'QPS dropped by '.round($dropRatio * 100).'% between snapshots.',
                        context: [
                            'from' => $qpsValues[$i - 1],
                            'to' => $qpsValues[$i],
                            'drop_ratio' => round($dropRatio, 4),
                        ],
                    );
                }
            }
        }

        return $anomalies;
    }

    /**
     * Return the last generated health summary.
     */
    public function getHealthSummary(): MonitorReportDTO
    {
        if ($this->lastReport !== null) {
            return $this->lastReport;
        }

        return new MonitorReportDTO(
            agent: 'monitoring',
            metrics: [],
            anomalies: [],
            healthScore: 0,
            healthGrade: 'N/A',
            tables: [],
            summary: ['status' => 'no_data'],
        );
    }

    // ── Private helpers ──────────────────────────────────────────

    /**
     * Analyze a single table for monitoring warnings.
     *
     * @return array{name: string, size_mb: float, row_count: int, index_count: int, primary_key: bool, warnings: array}
     */
    private function analyzeTable(TableDTO $table): array
    {
        $warnings = [];
        $hasPrimary = false;
        $indexColumns = [];

        foreach ($table->columns as $column) {
            if ($column->primary) {
                $hasPrimary = true;
            }
        }

        foreach ($table->indexes as $index) {
            $indexColumns = array_merge($indexColumns, $index->columns);
        }

        $indexCount = count($table->indexes);
        $uniqueIndexCount = count(array_filter($table->indexes, fn ($i) => $i->unique));

        // Flag: high row count without indexes
        if ($table->rowCount > 10000 && $indexCount === 0) {
            $warnings[] = [
                'severity' => 'high',
                'message' => "Table has {$table->rowCount} rows but no indexes — full table scan on every query",
            ];
        }

        // Flag: no primary key
        if (! $hasPrimary) {
            $warnings[] = [
                'severity' => 'high',
                'message' => 'Table has no primary key — may cause replication and performance issues',
            ];
        }

        // Flag: large table with only primary key (no secondary indexes)
        if ($table->rowCount > 50000 && $indexCount <= 1 && $hasPrimary) {
            $warnings[] = [
                'severity' => 'medium',
                'message' => "Large table ({$table->rowCount} rows) has only a primary key — consider adding indexes for query columns",
            ];
        }

        // Flag: very large table
        if ($table->sizeMb > 500) {
            $warnings[] = [
                'severity' => 'medium',
                'message' => "Table size is {$table->sizeMb}MB — consider partitioning or archiving old data",
            ];
        }

        return [
            'name' => $table->name,
            'size_mb' => round($table->sizeMb, 4),
            'row_count' => $table->rowCount,
            'index_count' => $indexCount,
            'unique_index_count' => $uniqueIndexCount,
            'primary_key' => $hasPrimary,
            'warnings' => $warnings,
        ];
    }

    private function computeScanRisk(TableDTO $table, int $indexCount): string
    {
        if ($indexCount === 0 && $table->rowCount > 10000) {
            return 'critical';
        }
        if ($indexCount === 0) {
            return 'high';
        }
        if ($indexCount === 1 && $table->rowCount > 50000) {
            return 'medium';
        }

        return 'low';
    }

    private function computeGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };
    }
}
