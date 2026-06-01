<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class MonitorReportDTO implements Arrayable
{
    /** @param array<PerformanceMetricDTO> $metrics */
    /** @param array<AnomalyDTO> $anomalies */
    public function __construct(
        public string $agent,
        public array $metrics,
        public array $anomalies,
        public int $healthScore,
        public string $healthGrade,
        public array $tables,
        public array $summary = [],
    ) {}

    public function toArray(): array
    {
        return [
            'agent' => $this->agent,
            'metrics' => array_map(fn (PerformanceMetricDTO $m) => $m->toArray(), $this->metrics),
            'anomalies' => array_map(fn (AnomalyDTO $a) => $a->toArray(), $this->anomalies),
            'health_score' => $this->healthScore,
            'health_grade' => $this->healthGrade,
            'tables' => $this->tables,
            'summary' => $this->summary,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            agent: (string) ($data['agent'] ?? 'monitoring'),
            metrics: array_map(
                fn (array $m) => $m instanceof PerformanceMetricDTO ? $m : PerformanceMetricDTO::fromArray($m),
                $data['metrics'] ?? [],
            ),
            anomalies: array_map(
                fn (array $a) => $a instanceof AnomalyDTO ? $a : AnomalyDTO::fromArray($a),
                $data['anomalies'] ?? [],
            ),
            healthScore: (int) ($data['health_score'] ?? 0),
            healthGrade: (string) ($data['health_grade'] ?? 'N/A'),
            tables: (array) ($data['tables'] ?? []),
            summary: (array) ($data['summary'] ?? []),
        );
    }
}
