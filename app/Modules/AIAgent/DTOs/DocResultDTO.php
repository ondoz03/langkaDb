<?php

declare(strict_types=1);

namespace App\Modules\AIAgent\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * DocResultDTO — the main result from DocumentationAgent::generateDataDictionary
 * Contains structured documentation for all tables and their columns.
 */
readonly class DocResultDTO implements Arrayable
{
    /** @param TableDocumentationDTO[] $tables */
    public function __construct(
        public string $database,
        public array $tables,
        public int $totalTables,
        public int $totalColumns,
        public int $totalRelations,
        public array $domains,
        public string $generatedAt,
        public string $format = 'json',
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            database: $data['database'],
            tables: array_map(
                fn (array $t) => $t instanceof TableDocumentationDTO ? $t : TableDocumentationDTO::fromArray($t),
                $data['tables'] ?? [],
            ),
            totalTables: (int) ($data['total_tables'] ?? count($data['tables'] ?? [])),
            totalColumns: (int) ($data['total_columns'] ?? 0),
            totalRelations: (int) ($data['total_relations'] ?? 0),
            domains: $data['domains'] ?? [],
            generatedAt: $data['generated_at'] ?? date('c'),
            format: $data['format'] ?? 'json',
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'database' => $this->database,
            'tables' => array_map(fn (TableDocumentationDTO $t) => $t->toArray(), $this->tables),
            'total_tables' => $this->totalTables,
            'total_columns' => $this->totalColumns,
            'total_relations' => $this->totalRelations,
            'domains' => $this->domains,
            'generated_at' => $this->generatedAt,
            'format' => $this->format,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Render the full data dictionary as a Markdown string
     */
    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = "# Data Dictionary: {$this->database}";
        $lines[] = '';
        $lines[] = "> Generated at: {$this->generatedAt}";
        $lines[] = '';
        $lines[] = '## Overview';
        $lines[] = '';
        $lines[] = '| Metric | Value |';
        $lines[] = '|--------|-------|';
        $lines[] = "| Total Tables | {$this->totalTables} |";
        $lines[] = "| Total Columns | {$this->totalColumns} |";
        $lines[] = "| Total Relations | {$this->totalRelations} |";
        $lines[] = '| Domains | '.implode(', ', $this->domains).' |';
        $lines[] = '';

        foreach ($this->tables as $table) {
            $lines[] = '---';
            $lines[] = '';
            $lines[] = "## `{$table->name}`";
            $lines[] = '';
            $lines[] = "**Domain:** {$table->domain}";
            $lines[] = '';
            $lines[] = $table->description;
            $lines[] = '';

            if (! empty($table->comment)) {
                $lines[] = "> *Table comment:* {$table->comment}";
                $lines[] = '';
            }

            $lines[] = "**Rows:** ~{$table->rowCount} | **Size:** {$table->sizeMb} MB";
            $lines[] = '';

            $lines[] = '### Columns';
            $lines[] = '';
            $lines[] = $table->toMarkdownTable();
            $lines[] = '';

            if (! empty($table->relationships)) {
                $lines[] = '### Relationships';
                $lines[] = '';
                $lines[] = '| Name | From | To | Type |';
                $lines[] = '|------|------|----|------|';
                foreach ($table->relationships as $rel) {
                    $lines[] = sprintf(
                        '| %s | %s.%s | %s.%s | %s |',
                        $rel['name'] ?? '',
                        $rel['from_table'] ?? '',
                        $rel['from_column'] ?? '',
                        $rel['to_table'] ?? '',
                        $rel['to_column'] ?? '',
                        $rel['type'] ?? 'belongs_to',
                    );
                }
                $lines[] = '';
            }

            if (! empty($table->tags)) {
                $lines[] = '**Tags:** '.implode(', ', array_map(fn ($t) => "`{$t}`", $table->tags));
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }
}
