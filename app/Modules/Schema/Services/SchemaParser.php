<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\TableDTO;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

class SchemaParser
{
    public function parse(AbstractSchemaManager $schemaManager): array
    {
        $tables = $schemaManager->listTables();

        return array_map(fn (Table $table) => $this->parseTable($table), $tables);
    }

    private function resolveTypeName(Type $type): string
    {
        $class = get_class($type);
        $parts = explode('\\', $class);
        $shortName = end($parts);

        return str_replace('Type', '', $shortName);
    }

    private function parseTable(Table $table): TableDTO
    {
        $columns = array_map(function ($column) use ($table) {
            $primary = $table->getPrimaryKey();
            $primaryColumns = $primary ? $primary->getColumns() : [];

            $typeName = $this->resolveTypeName($column->getType());
            $default = $column->getDefault();

            if ($default instanceof \Doctrine\DBAL\Schema\DefaultExpression) {
                $class = get_class($default);
                $parts = explode('\\', $class);
                $default = end($parts);
            }

            return new ColumnDTO(
                name: $column->getName(),
                type: $typeName,
                nullable: !$column->getNotnull(),
                default: $default,
                primary: in_array($column->getName(), $primaryColumns, true),
                comment: $column->getComment(),
            );
        }, $table->getColumns());

        $indexes = array_map(fn ($index) => new IndexDTO(
            name: $index->getName(),
            columns: $index->getColumns(),
            unique: $index->isUnique(),
            type: $index->isPrimary() ? 'primary' : ($index->isUnique() ? 'unique' : 'index'),
        ), $table->getIndexes());

        return new TableDTO(
            name: $table->getName(),
            columns: $columns,
            indexes: $indexes,
            rowCount: 0,
            sizeMb: 0,
            comment: null,
        );
    }
}
