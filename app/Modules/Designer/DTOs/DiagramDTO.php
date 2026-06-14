<?php

declare(strict_types=1);

namespace App\Modules\Designer\DTOs;

use App\Modules\Designer\Models\Diagram;
use Illuminate\Contracts\Support\Arrayable;

readonly class DiagramDTO implements Arrayable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?string $connectionId,
        public ?array $layoutData,
        public array $nodes,
        public array $relations,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromModel(Diagram $diagram): self
    {
        return new self(
            id: $diagram->id,
            name: $diagram->name,
            description: $diagram->description,
            connectionId: $diagram->connection_id,
            layoutData: $diagram->layout_data,
            nodes: $diagram->relationLoaded('nodes')
                ? $diagram->nodes->map(fn ($n) => DiagramNodeDTO::fromModel($n))->toArray()
                : [],
            relations: $diagram->relationLoaded('relations')
                ? $diagram->relations->map(fn ($r) => DiagramRelationDTO::fromModel($r))->toArray()
                : [],
            createdAt: $diagram->created_at?->toIso8601String() ?? '',
            updatedAt: $diagram->updated_at?->toIso8601String() ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'connection_id' => $this->connectionId,
            'layout_data' => $this->layoutData,
            'nodes' => $this->nodes,
            'relations' => $this->relations,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
