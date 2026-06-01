<?php

declare(strict_types=1);

namespace App\Modules\Query\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class ExplainResultDTO implements Arrayable
{
    /**
     * @param ExplainNodeDTO[] $tree
     * @param array<string, mixed> $costBreakdown
     * @param string[] $suggestions
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public string $query,
        public array $tree,
        public array $costBreakdown,
        public array $suggestions = [],
        public array $raw = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            query: $data['query'],
            tree: array_map(fn (array $n) => ExplainNodeDTO::fromArray($n), $data['tree'] ?? []),
            costBreakdown: $data['cost_breakdown'] ?? [],
            suggestions: $data['suggestions'] ?? [],
            raw: $data['raw'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'tree' => array_map(fn (ExplainNodeDTO $n) => $n->toArray(), $this->tree),
            'cost_breakdown' => $this->costBreakdown,
            'suggestions' => $this->suggestions,
        ];
    }
}
