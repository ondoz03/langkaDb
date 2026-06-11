<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\SchemaContextDTO;

class SchemaChunker
{
    /** Approx chars per token for English text */
    private const float CHARS_PER_TOKEN = 3.5;

    public function __construct(
        private readonly int $maxTokensPerChunk = 8000,
    ) {}

    /**
     * Split schema context into token-safe chunks.
     *
     * @return array<int, array{type: string, content: array}>
     */
    public function chunk(SchemaContextDTO $context): array
    {
        $fullJson = $context->toJson();
        $estimatedTokens = (int) ceil(strlen($fullJson) / self::CHARS_PER_TOKEN);

        if ($estimatedTokens <= $this->maxTokensPerChunk) {
            return [
                [
                    'type' => 'full',
                    'content' => $context->toArray(),
                    'estimated_tokens' => $estimatedTokens,
                ],
            ];
        }

        $chunks = [];

        // 1. Summary chunk (always first)
        $summaryChunk = [
            'database' => $context->database,
            'summary' => $context->summary,
            'table_list' => array_map(fn ($t) => ['name' => $t->name, 'row_count' => $t->rowCount, 'size_mb' => $t->sizeMb], $context->tables),
        ];
        $chunks[] = [
            'type' => 'summary',
            'content' => $summaryChunk,
            'estimated_tokens' => (int) ceil(strlen(json_encode($summaryChunk)) / self::CHARS_PER_TOKEN),
        ];

        // 2. Table chunks (grouped by token limit)
        $tableChunks = $this->chunkTables($context->tables);
        foreach ($tableChunks as $idx => $tables) {
            $chunks[] = [
                'type' => 'tables',
                'content' => [
                    'chunk_index' => $idx + 1,
                    'total_chunks' => count($tableChunks),
                    'tables' => array_map(fn ($t) => $t->toArray(), $tables),
                ],
                'estimated_tokens' => (int) ceil(strlen(json_encode($tables)) / self::CHARS_PER_TOKEN),
            ];
        }

        // 3. Relations chunk (if present)
        if (count($context->relations) > 0) {
            $relationsArray = array_map(fn ($r) => $r->toArray(), $context->relations);
            $chunks[] = [
                'type' => 'relations',
                'content' => [
                    'relations' => $relationsArray,
                ],
                'estimated_tokens' => (int) ceil(strlen(json_encode($relationsArray)) / self::CHARS_PER_TOKEN),
            ];
        }

        return $chunks;
    }

    /**
     * @param  array  $tables  array of TableDTO
     * @return array<int, array> grouped tables
     */
    private function chunkTables(array $tables): array
    {
        $groups = [];
        $currentGroup = [];
        $currentTokens = 0;

        foreach ($tables as $table) {
            $tableJson = json_encode($table->toArray());
            $tableTokens = (int) ceil(strlen($tableJson) / self::CHARS_PER_TOKEN);

            // If a single table exceeds limit, it gets its own chunk
            if ($tableTokens > $this->maxTokensPerChunk) {
                if (! empty($currentGroup)) {
                    $groups[] = $currentGroup;
                }
                $groups[] = [$table];
                $currentGroup = [];
                $currentTokens = 0;

                continue;
            }

            if ($currentTokens + $tableTokens > $this->maxTokensPerChunk && ! empty($currentGroup)) {
                $groups[] = $currentGroup;
                $currentGroup = [];
                $currentTokens = 0;
            }

            $currentGroup[] = $table;
            $currentTokens += $tableTokens;
        }

        if (! empty($currentGroup)) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Build a concise summary-only payload for quick AI prompts.
     */
    public function summarize(SchemaContextDTO $context): array
    {
        return [
            'database' => $context->database,
            'summary' => $context->summary,
            'table_names' => array_map(fn ($t) => $t->name, $context->tables),
            'relation_count' => count($context->relations),
        ];
    }
}
