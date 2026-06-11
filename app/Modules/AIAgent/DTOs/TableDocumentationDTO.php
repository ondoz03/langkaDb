<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * TableDocumentationDTO — documents a single database table with its columns, relationships, and business context
 */
readonly class TableDocumentationDTO implements Arrayable
{
    /** @param ColumnDocumentationDTO[] $columns */
    public function __construct(
        public string $name,
        public array $columns,
        public string $description,
        public string $domain,
        public int $rowCount,
        public float $sizeMb,
        public ?string $comment,
        public array $relationships = [],
        public array $indexes = [],
        public array $tags = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            columns: array_map(
                fn (array $c) => $c instanceof ColumnDocumentationDTO ? $c : ColumnDocumentationDTO::fromArray($c),
                $data['columns'] ?? [],
            ),
            description: $data['description'] ?? '',
            domain: $data['domain'] ?? 'general',
            rowCount: (int) ($data['row_count'] ?? 0),
            sizeMb: (float) ($data['size_mb'] ?? 0),
            comment: $data['comment'] ?? null,
            relationships: $data['relationships'] ?? [],
            indexes: $data['indexes'] ?? [],
            tags: $data['tags'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'columns' => array_map(fn (ColumnDocumentationDTO $c) => $c->toArray(), $this->columns),
            'description' => $this->description,
            'domain' => $this->domain,
            'row_count' => $this->rowCount,
            'size_mb' => $this->sizeMb,
            'comment' => $this->comment,
            'relationships' => $this->relationships,
            'indexes' => $this->indexes,
            'tags' => $this->tags,
        ];
    }

    /** Format column documentation as a markdown table fragment */
    public function toMarkdownTable(): string
    {
        $rows = [];
        foreach ($this->columns as $col) {
            $flags = [];
            if ($col->primary) {
                $flags[] = 'PK';
            }
            if ($col->nullable) {
                $flags[] = 'NULL';
            }

            $example = $col->exampleValue ? "e.g. `{$col->exampleValue}`" : '';
            $rows[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $col->name,
                $col->type,
                ! empty($flags) ? '`'.implode(' ', $flags).'`' : '',
                $col->description,
                $example,
            );
        }

        $header = '| Column | Type | Flags | Description | Example |';
        $separator = '|--------|------|-------|-------------|---------|';

        return implode("\n", [$header, $separator, ...$rows]);
    }
}
