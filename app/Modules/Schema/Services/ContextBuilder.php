<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\SchemaContextDTO;

class ContextBuilder
{
    public function __construct(
        private readonly SchemaScanner $scanner,
        private readonly SchemaParser $parser,
        private readonly RelationMapper $relationMapper,
    ) {}

    public function build(string $connectionId, string $databaseName): SchemaContextDTO
    {
        $schemaManager = $this->scanner->scan($connectionId);
        $tables = $this->parser->parse($schemaManager);
        $relations = $this->relationMapper->map($schemaManager);

        $summary = [
            'total_tables' => count($tables),
            'total_relations' => count($relations),
            'total_indexes' => array_sum(array_map(fn ($t) => count($t->indexes), $tables)),
        ];

        return new SchemaContextDTO(
            database: $databaseName,
            tables: $tables,
            relations: $relations,
            summary: $summary,
        );
    }
}
