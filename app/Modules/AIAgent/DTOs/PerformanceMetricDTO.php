<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class PerformanceMetricDTO implements Arrayable
{
    public function __construct(
        public float $qps,
        public float $latencyMs,
        public int $connectionCount,
        public string $measuredAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            qps: (float) ($data['qps'] ?? 0),
            latencyMs: (float) ($data['latency_ms'] ?? 0),
            connectionCount: (int) ($data['connection_count'] ?? 0),
            measuredAt: (string) ($data['measured_at'] ?? date('c')),
        );
    }

    public function toArray(): array
    {
        return [
            'qps' => $this->qps,
            'latency_ms' => $this->latencyMs,
            'connection_count' => $this->connectionCount,
            'measured_at' => $this->measuredAt,
        ];
    }
}
