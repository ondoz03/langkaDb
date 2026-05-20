<?php

declare(strict_types=1);

namespace App\Modules\Schema\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class IndexDTO implements Arrayable
{
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique,
        public string $type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            columns: $data['columns'],
            unique: (bool) ($data['unique'] ?? false),
            type: $data['type'] ?? 'index',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => $this->columns,
            'unique' => $this->unique,
            'type' => $this->type,
        ];
    }
}
