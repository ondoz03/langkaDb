<?php

declare(strict_types=1);

namespace App\Modules\Schema\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class RelationDTO implements Arrayable
{
    public function __construct(
        public string $name,
        public string $fromTable,
        public string $fromColumn,
        public string $toTable,
        public string $toColumn,
        public string $type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            fromTable: $data['from_table'],
            fromColumn: $data['from_column'],
            toTable: $data['to_table'],
            toColumn: $data['to_column'],
            type: $data['type'] ?? 'belongs_to',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'from_table' => $this->fromTable,
            'from_column' => $this->fromColumn,
            'to_table' => $this->toTable,
            'to_column' => $this->toColumn,
            'type' => $this->type,
        ];
    }
}
