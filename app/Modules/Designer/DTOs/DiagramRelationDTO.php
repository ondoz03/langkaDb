<?php

declare(strict_types=1);

namespace App\Modules\Designer\DTOs;

use App\Modules\Designer\Models\DiagramRelation;
use Illuminate\Contracts\Support\Arrayable;

readonly class DiagramRelationDTO implements Arrayable
{
    public function __construct(
        public string $id,
        public string $diagramId,
        public string $fromTable,
        public string $fromColumn,
        public string $toTable,
        public string $toColumn,
        public string $type,
        public ?string $name,
    ) {}

    public static function fromModel(DiagramRelation $relation): self
    {
        return new self(
            id: $relation->id,
            diagramId: $relation->diagram_id,
            fromTable: $relation->from_table,
            fromColumn: $relation->from_column,
            toTable: $relation->to_table,
            toColumn: $relation->to_column,
            type: $relation->type,
            name: $relation->name,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'diagram_id' => $this->diagramId,
            'from_table' => $this->fromTable,
            'from_column' => $this->fromColumn,
            'to_table' => $this->toTable,
            'to_column' => $this->toColumn,
            'type' => $this->type,
            'name' => $this->name,
        ];
    }
}
