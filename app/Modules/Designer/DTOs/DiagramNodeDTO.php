<?php

declare(strict_types=1);

namespace App\Modules\Designer\DTOs;

use App\Modules\Designer\Models\DiagramNode;
use Illuminate\Contracts\Support\Arrayable;

readonly class DiagramNodeDTO implements Arrayable
{
    public function __construct(
        public string $id,
        public string $diagramId,
        public string $tableName,
        public float $xPos,
        public float $yPos,
        public ?array $metadata,
    ) {}

    public static function fromModel(DiagramNode $node): self
    {
        return new self(
            id: $node->id,
            diagramId: $node->diagram_id,
            tableName: $node->table_name,
            xPos: $node->x_pos,
            yPos: $node->y_pos,
            metadata: $node->metadata,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            diagramId: $data['diagram_id'] ?? '',
            tableName: $data['table_name'],
            xPos: (float) ($data['x_pos'] ?? 0),
            yPos: (float) ($data['y_pos'] ?? 0),
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'diagram_id' => $this->diagramId,
            'table_name' => $this->tableName,
            'x_pos' => $this->xPos,
            'y_pos' => $this->yPos,
            'metadata' => $this->metadata,
        ];
    }
}
