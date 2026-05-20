<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\RelationDTO;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class RelationMapper
{
    public function map(AbstractSchemaManager $schemaManager): array
    {
        $tables = $schemaManager->listTables();
        $relations = [];

        foreach ($tables as $table) {
            $foreignKeys = $table->getForeignKeys();

            foreach ($foreignKeys as $fk) {
                $localColumns = $fk->getLocalColumns();
                $foreignColumns = $fk->getForeignColumns();

                foreach ($localColumns as $i => $localCol) {
                    $foreignCol = $foreignColumns[$i] ?? null;

                    if (!$foreignCol) {
                        continue;
                    }

                    $relations[] = new RelationDTO(
                        name: $fk->getName(),
                        fromTable: $table->getName(),
                        fromColumn: $localCol,
                        toTable: $fk->getForeignTableName(),
                        toColumn: $foreignCol,
                        type: 'belongs_to',
                    );
                }
            }
        }

        return $relations;
    }
}
