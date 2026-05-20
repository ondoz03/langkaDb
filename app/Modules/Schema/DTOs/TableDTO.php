<?php

declare(strict_types=1);

namespace App\Modules\Schema\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class TableDTO implements Arrayable
{
    public function __construct(
        public string $name,
        public array $columns,
        public array $indexes,
        public int $rowCount,
        public float $sizeMb,
        public ?string $comment,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            columns: array_map(fn ($c) => $c instanceof ColumnDTO ? $c : ColumnDTO::fromArray($c), $data['columns'] ?? []),
            indexes: array_map(fn ($i) => $i instanceof IndexDTO ? $i : IndexDTO::fromArray($i), $data['indexes'] ?? []),
            rowCount: (int) ($data['row_count'] ?? 0),
            sizeMb: (float) ($data['size_mb'] ?? 0),
            comment: $data['comment'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => array_map(fn (ColumnDTO $c) => $c->toArray(), $this->columns),
            'indexes' => array_map(fn (IndexDTO $i) => $i->toArray(), $this->indexes),
            'row_count' => $this->rowCount,
            'size_mb' => $this->sizeMb,
            'comment' => $this->comment,
        ];
    }
}
