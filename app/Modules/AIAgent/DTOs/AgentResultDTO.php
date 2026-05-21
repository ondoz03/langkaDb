<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class AgentResultDTO implements Arrayable
{
    public function __construct(
        public string $agent,
        public array $findings,
        public array $recommendations,
        public int $score,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'agent' => $this->agent,
            'findings' => $this->findings,
            'recommendations' => $this->recommendations,
            'score' => $this->score,
            'metadata' => $this->metadata,
        ];
    }
}
