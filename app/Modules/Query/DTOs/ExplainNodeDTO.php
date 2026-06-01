<?php

declare(strict_types=1);

namespace App\Modules\Query\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class ExplainNodeDTO implements Arrayable
{
    /**
     * @param array<string, mixed> $costInfo
     * @param string[] $usedColumns
     * @param ExplainNodeDTO[] $children
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $table,
        public float $cost,
        public int $rows,
        public float $filtered,
        public string $accessType,
        public ?string $key,
        public ?string $extra,
        public array $costInfo = [],
        public array $usedColumns = [],
        public array $children = [],
        public array $extraFields = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            table: $data['table'] ?? null,
            cost: (float) ($data['cost'] ?? 0),
            rows: (int) ($data['rows'] ?? 0),
            filtered: (float) ($data['filtered'] ?? 100),
            accessType: $data['access_type'],
            key: $data['key'] ?? null,
            extra: $data['extra'] ?? null,
            costInfo: $data['cost_info'] ?? [],
            usedColumns: $data['used_columns'] ?? [],
            children: array_map(
                fn (array $child) => self::fromArray($child),
                $data['children'] ?? [],
            ),
            extraFields: $data['extra_fields'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'table' => $this->table,
            'cost' => $this->cost,
            'rows' => $this->rows,
            'filtered' => $this->filtered,
            'access_type' => $this->accessType,
            'key' => $this->key,
            'extra' => $this->extra,
            'cost_info' => $this->costInfo,
            'used_columns' => $this->usedColumns,
            'children' => array_map(fn (ExplainNodeDTO $c) => $c->toArray(), $this->children),
            'extra_fields' => $this->extraFields,
        ];
    }
}
