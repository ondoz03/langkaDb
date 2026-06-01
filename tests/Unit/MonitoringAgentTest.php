<?php

declare(strict_types=1);

use App\Modules\AIAgent\Agents\MonitoringAgent;
use App\Modules\AIAgent\DTOs\AnomalyDTO;
use App\Modules\AIAgent\DTOs\MonitorReportDTO;
use App\Modules\AIAgent\DTOs\PerformanceMetricDTO;
use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\SchemaContextDTO;
use App\Modules\Schema\DTOs\TableDTO;

// ─── Helpers ────────────────────────────────────────────────

function makeTable(string $name, int $rows, float $sizeMb, array $indexes = [], array $columns = []): TableDTO
{
    if (empty($columns)) {
        $columns = [
            new ColumnDTO(name: 'id', type: 'int', nullable: false, default: null, primary: true, comment: null),
            new ColumnDTO(name: 'name', type: 'varchar(255)', nullable: true, default: null, primary: false, comment: null),
        ];
    }

    return new TableDTO(
        name: $name,
        columns: $columns,
        indexes: $indexes,
        rowCount: $rows,
        sizeMb: $sizeMb,
        comment: null,
    );
}

function makeContext(string $db, array $tables): SchemaContextDTO
{
    return new SchemaContextDTO(
        database: $db,
        tables: $tables,
        relations: [],
        summary: ['table_count' => count($tables)],
    );
}

// ─── analyze() ──────────────────────────────────────────────

test('analyze returns healthy report for well-indexed tables', function () {
    $agent = new MonitoringAgent();

    $tables = [
        makeTable('users', 1000, 5.0, [
            new IndexDTO(name: 'primary', columns: ['id'], unique: true, type: 'BTREE'),
            new IndexDTO(name: 'idx_email', columns: ['email'], unique: true, type: 'BTREE'),
        ]),
        makeTable('posts', 5000, 25.0, [
            new IndexDTO(name: 'primary', columns: ['id'], unique: true, type: 'BTREE'),
            new IndexDTO(name: 'idx_user', columns: ['user_id'], unique: false, type: 'BTREE'),
        ]),
    ];

    $result = $agent->analyze(makeContext('test_db', $tables));

    expect($result->agent)->toBe('monitoring');
    expect($result->score)->toBeGreaterThanOrEqual(70);
    expect($result->findings)->toBeArray();
    expect($result->recommendations)->toBeArray();
});

test('analyze flags high-row tables without indexes', function () {
    $agent = new MonitoringAgent();

    $tables = [
        makeTable('logs', 500000, 200.0, []),  // No indexes, high rows
        makeTable('audit', 100000, 50.0, []),   // No indexes, high rows
    ];

    $result = $agent->analyze(makeContext('test_db', $tables));

    // Security placeholder (85 * 0.15 = 12.75) keeps a minimum floor
    expect($result->score)->toBeLessThanOrEqual(25);

    $highFindings = array_filter($result->findings, fn ($f) => $f['severity'] === 'high');
    expect($highFindings)->not->toBeEmpty();

    // Should flag the missing indexes
    $hasHighRowsWarning = false;
    foreach ($result->findings as $f) {
        if (str_contains($f['message'], 'have no indexes')) {
            $hasHighRowsWarning = true;
            break;
        }
    }
    expect($hasHighRowsWarning)->toBeTrue();
});

test('analyze handles empty table list', function () {
    $agent = new MonitoringAgent();
    $result = $agent->analyze(makeContext('empty_db', []));

    expect($result->score)->toBeGreaterThanOrEqual(0);
    expect($result->metadata['total_tables'])->toBe(0);
    expect($result->findings)->toBeArray();
    expect($result->findings)->toBeEmpty();
});

test('analyze detects no-primary-key tables', function () {
    $agent = new MonitoringAgent();

    $columns = [
        new ColumnDTO(name: 'data', type: 'text', nullable: true, default: null, primary: false, comment: null),
    ];

    $tables = [
        new TableDTO(
            name: 'no_pk_table',
            columns: $columns,
            indexes: [],
            rowCount: 5000,
            sizeMb: 10.0,
            comment: null,
        ),
    ];

    $result = $agent->analyze(makeContext('test_db', $tables));

    $pkWarnings = array_filter($result->findings, fn ($f) => str_contains($f['message'], 'no primary key'));
    expect($pkWarnings)->not->toBeEmpty();
});

// ─── analyzePerformance() ─────────────────────────────────┐

test('analyzePerformance returns report with metrics', function () {
    $agent = new MonitoringAgent();

    $metrics = [
        ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20, 'measured_at' => '2026-01-01T00:00:00Z'],
        ['qps' => 120, 'latency_ms' => 65, 'connection_count' => 25, 'measured_at' => '2026-01-01T00:01:00Z'],
        ['qps' => 110, 'latency_ms' => 55, 'connection_count' => 22, 'measured_at' => '2026-01-01T00:02:00Z'],
    ];

    $report = $agent->analyzePerformance($metrics);

    expect($report)->toBeInstanceOf(MonitorReportDTO::class);
    expect($report->agent)->toBe('monitoring');
    expect($report->metrics)->toHaveCount(3);
    expect($report->summary['avg_qps'])->toBeGreaterThan(0);
});

test('analyzePerformance detects high latency', function () {
    $agent = new MonitoringAgent();

    $metrics = [
        ['qps' => 100, 'latency_ms' => 1200, 'connection_count' => 20],
        ['qps' => 90, 'latency_ms' => 1500, 'connection_count' => 22],
        ['qps' => 80, 'latency_ms' => 2000, 'connection_count' => 25],
    ];

    $report = $agent->analyzePerformance($metrics);

    expect($report->anomalies)->not->toBeEmpty();

    $latencyAnomalies = array_filter(
        $report->anomalies,
        fn (AnomalyDTO $a) => $a->type === 'high_latency',
    );
    expect($latencyAnomalies)->not->toBeEmpty();
});

test('analyzePerformance returns insufficient_data for single metric', function () {
    $agent = new MonitoringAgent();

    $metrics = [
        ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20],
    ];

    $report = $agent->analyzePerformance($metrics);

    expect($report->summary['status'])->toBe('insufficient_data');
});

test('analyzePerformance accepts PerformanceMetricDTO objects', function () {
    $agent = new MonitoringAgent();

    $metrics = [
        new PerformanceMetricDTO(qps: 100.0, latencyMs: 50.0, connectionCount: 20, measuredAt: '2026-01-01T00:00:00Z'),
        new PerformanceMetricDTO(qps: 120.0, latencyMs: 65.0, connectionCount: 25, measuredAt: '2026-01-01T00:01:00Z'),
    ];

    $report = $agent->analyzePerformance($metrics);

    expect($report->metrics)->toHaveCount(2);
});

// ─── trackGrowth() ─────────────────────────────────────────

test('trackGrowth returns table growth data sorted by size', function () {
    $agent = new MonitoringAgent();

    $tables = [
        makeTable('small', 100, 1.0, [new IndexDTO(name: 'primary', columns: ['id'], unique: true, type: 'BTREE')]),
        makeTable('large', 500000, 300.0, []),
        makeTable('medium', 10000, 50.0, [new IndexDTO(name: 'primary', columns: ['id'], unique: true, type: 'BTREE')]),
    ];

    $growth = $agent->trackGrowth(makeContext('test_db', $tables));

    expect($growth)->toHaveCount(3);
    // Should be sorted by size descending
    expect($growth[0]['name'])->toBe('large');
    expect($growth[0]['size_mb'])->toBe(300.0);
    expect($growth[0]['scan_risk'])->toBe('critical');
    expect($growth[2]['name'])->toBe('small');
});

test('trackGrowth returns empty array for no tables', function () {
    $agent = new MonitoringAgent();
    $growth = $agent->trackGrowth(makeContext('empty_db', []));

    expect($growth)->toBeArray();
    expect($growth)->toBeEmpty();
});

// ─── detectAnomalies() ─────────────────────────────────────

test('detectAnomalies detects latency spikes', function () {
    $agent = new MonitoringAgent();

    $history = [
        ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20],
        ['qps' => 110, 'latency_ms' => 55, 'connection_count' => 22],
        ['qps' => 105, 'latency_ms' => 48, 'connection_count' => 21],
        ['qps' => 108, 'latency_ms' => 52, 'connection_count' => 23],
        ['qps' => 95, 'latency_ms' => 2000, 'connection_count' => 25],  // massive spike
        ['qps' => 107, 'latency_ms' => 51, 'connection_count' => 22],
        ['qps' => 102, 'latency_ms' => 49, 'connection_count' => 20],
    ];

    $anomalies = $agent->detectAnomalies($history);

    expect($anomalies)->not->toBeEmpty();

    $spikes = array_filter($anomalies, fn (AnomalyDTO $a) => $a->type === 'latency_spike');
    expect($spikes)->not->toBeEmpty();
});

test('detectAnomalies detects connection growth', function () {
    $agent = new MonitoringAgent();

    $history = [
        ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20],
        ['qps' => 110, 'latency_ms' => 55, 'connection_count' => 20],
        ['qps' => 105, 'latency_ms' => 52, 'connection_count' => 25],
        ['qps' => 95, 'latency_ms' => 60, 'connection_count' => 200], // +175 jump from previous
        ['qps' => 90, 'latency_ms' => 58, 'connection_count' => 195],
    ];

    $anomalies = $agent->detectAnomalies($history);

    expect($anomalies)->not->toBeEmpty();

    $growthAnomalies = array_filter($anomalies, fn (AnomalyDTO $a) => $a->type === 'connection_growth');
    expect($growthAnomalies)->not->toBeEmpty();
});

test('detectAnomalies returns insufficient_history for few data points', function () {
    $agent = new MonitoringAgent();

    $anomalies = $agent->detectAnomalies([
        ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20],
    ]);

    expect($anomalies)->toHaveCount(1);
    expect($anomalies[0]->type)->toBe('insufficient_history');
});

test('detectAnomalies detects QPS drop', function () {
    $agent = new MonitoringAgent();

    $history = [
        ['qps' => 200, 'latency_ms' => 50, 'connection_count' => 20],
        ['qps' => 210, 'latency_ms' => 48, 'connection_count' => 22],
        ['qps' => 30, 'latency_ms' => 55, 'connection_count' => 22],  // >50% drop from 210
    ];

    $anomalies = $agent->detectAnomalies($history);

    expect($anomalies)->not->toBeEmpty();

    $qpsDrops = array_filter($anomalies, fn (AnomalyDTO $a) => $a->type === 'qps_drop');
    expect($qpsDrops)->not->toBeEmpty();
});

// ─── getHealthSummary() ────────────────────────────────────

test('getHealthSummary returns N/A when no analysis performed', function () {
    $agent = new MonitoringAgent();
    $summary = $agent->getHealthSummary();

    expect($summary->healthGrade)->toBe('N/A');
    expect($summary->summary['status'])->toBe('no_data');
});

test('getHealthSummary returns last report after analyze', function () {
    $agent = new MonitoringAgent();

    $agent->analyze(makeContext('after_analyze_db', [
        makeTable('test', 1000, 5.0, [
            new IndexDTO(name: 'primary', columns: ['id'], unique: true, type: 'BTREE'),
        ]),
    ]));

    $summary = $agent->getHealthSummary();

    expect($summary)->toBeInstanceOf(MonitorReportDTO::class);
    expect($summary->healthScore)->toBeGreaterThanOrEqual(0);
    expect($summary->healthGrade)->not->toBe('N/A');
});

// ─── DTO Tests ─────────────────────────────────────────────

test('PerformanceMetricDTO fromArray and toArray round-trips', function () {
    $data = ['qps' => 150.5, 'latency_ms' => 45.2, 'connection_count' => 30, 'measured_at' => '2026-06-01T12:00:00Z'];
    $dto = PerformanceMetricDTO::fromArray($data);

    expect($dto->qps)->toBe(150.5);
    expect($dto->toArray()['latency_ms'])->toBe(45.2);
    expect($dto->toArray()['connection_count'])->toBe(30);
});

test('AnomalyDTO fromArray and toArray round-trips', function () {
    $data = ['type' => 'latency_spike', 'severity' => 'high', 'message' => 'High latency', 'table' => 'orders'];
    $dto = AnomalyDTO::fromArray($data);

    expect($dto->type)->toBe('latency_spike');
    expect($dto->toArray()['table'])->toBe('orders');
});

test('MonitorReportDTO fromArray and toArray round-trips', function () {
    $data = [
        'agent' => 'monitoring',
        'metrics' => [
            ['qps' => 100, 'latency_ms' => 50, 'connection_count' => 20, 'measured_at' => '2026-06-01T00:00:00Z'],
        ],
        'anomalies' => [
            ['type' => 'high_latency', 'severity' => 'high', 'message' => 'Peak latency'],
        ],
        'health_score' => 85,
        'health_grade' => 'B',
        'tables' => [],
        'summary' => ['avg_qps' => 100],
    ];

    $dto = MonitorReportDTO::fromArray($data);

    expect($dto->agent)->toBe('monitoring');
    expect($dto->healthGrade)->toBe('B');
    expect($dto->metrics)->toHaveCount(1);
    expect($dto->anomalies)->toHaveCount(1);
});
