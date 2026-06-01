<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * ColumnDocumentationDTO — documents a single database column with description and metadata
 */
readonly class ColumnDocumentationDTO implements Arrayable
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public ?string $default,
        public bool $primary,
        public ?string $comment,
        public string $description,
        public array $tags = [],
        public ?string $exampleValue = null,
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
            description: $data['description'] ?? '',
            tags: $data['tags'] ?? [],
            exampleValue: $data['example_value'] ?? null,
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
            'description' => $this->description,
            'tags' => $this->tags,
            'example_value' => $this->exampleValue,
        ];
    }
}
