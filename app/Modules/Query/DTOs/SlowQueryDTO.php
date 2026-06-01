<?php

declare(strict_types=1);

namespace App\Modules\Query\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class SlowQueryDTO implements Arrayable
{
    public function __construct(
        public string $digest,
        public string $queryText,
        public float $avgTimerWait,
        public int $rowsExaminedAvg,
        public string $lastSeen,
        public int $frequency,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            digest: $data['digest'],
            queryText: $data['query_text'],
            avgTimerWait: (float) ($data['avg_timer_wait'] ?? 0),
            rowsExaminedAvg: (int) ($data['rows_examined_avg'] ?? 0),
            lastSeen: $data['last_seen'],
            frequency: (int) ($data['frequency'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'digest' => $this->digest,
            'query_text' => $this->queryText,
            'avg_timer_wait' => $this->avgTimerWait,
            'rows_examined_avg' => $this->rowsExaminedAvg,
            'last_seen' => $this->lastSeen,
            'frequency' => $this->frequency,
        ];
    }
}
