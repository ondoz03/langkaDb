<?php

declare(strict_types=1);

namespace App\Modules\Schema\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class SchemaContextDTO implements Arrayable
{
    public function __construct(
        public string $database,
        public array $tables,
        public array $relations,
        public array $summary,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            database: $data['database'],
            tables: array_map(fn ($t) => $t instanceof TableDTO ? $t : TableDTO::fromArray($t), $data['tables'] ?? []),
            relations: array_map(fn ($r) => $r instanceof RelationDTO ? $r : RelationDTO::fromArray($r), $data['relations'] ?? []),
            summary: $data['summary'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'database' => $this->database,
            'tables' => array_map(fn (TableDTO $t) => $t->toArray(), $this->tables),
            'relations' => array_map(fn (RelationDTO $r) => $r->toArray(), $this->relations),
            'summary' => $this->summary,
        ];
    }
}
