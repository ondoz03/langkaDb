<?php

declare(strict_types=1);

namespace App\Modules\Schema\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class ColumnDTO implements Arrayable
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public ?string $default,
        public bool $primary,
        public ?string $comment,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            nullable: (bool) ($data['nullable'] ?? false),
            default: $data['default'] ?? null,
            primary: (bool) ($data['primary'] ?? false),
            comment: $data['comment'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'primary' => $this->primary,
            'comment' => $this->comment,
        ];
    }
}
