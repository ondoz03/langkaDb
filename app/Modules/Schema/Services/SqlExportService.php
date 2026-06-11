<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\RelationDTO;
use App\Modules\Schema\DTOs\TableDTO;

/**
 * SqlExportService — Generate MySQL DDL from parsed schema DTOs
 */
class SqlExportService
{
    private const TYPE_MAP = [
        'Integer' => 'INT',
        'SmallInt' => 'SMALLINT',
        'BigInt' => 'BIGINT',
        'TinyInt' => 'TINYINT',
        'Float' => 'FLOAT',
        'Double' => 'DOUBLE',
        'Decimal' => 'DECIMAL',
        'String' => 'VARCHAR(255)',
        'Text' => 'TEXT',
        'Boolean' => 'TINYINT(1)',
        'DateTime' => 'DATETIME',
        'DateTimeImmutable' => 'DATETIME',
        'Date' => 'DATE',
        'Time' => 'TIME',
        'Timestamp' => 'TIMESTAMP',
        'Year' => 'YEAR',
        'Binary' => 'BINARY',
        'Blob' => 'BLOB',
        'Guid' => 'CHAR(36)',
        'Json' => 'JSON',
        'SimpleArray' => 'TEXT',
        'Array' => 'JSON',
        'Object' => 'TEXT',
    ];

    /**
     * Generate CREATE TABLE statement for a single table, including FK constraints.
     */
    public function generateCreateTable(TableDTO $table, array $relations = []): string
    {
        $lines = [];
        $lines[] = "CREATE TABLE `{$table->name}` (";
        $columnDefs = [];

        $pkColumns = array_values(array_filter(
            $table->columns,
            fn (ColumnDTO $c) => $c->primary,
        ));
        $isSingleAutoIncrement = count($pkColumns) === 1;

        foreach ($table->columns as $column) {
            $def = "  `{$column->name}` ".$this->mapType($column->type);

            if ($column->primary && $isSingleAutoIncrement) {
                $def .= ' NOT NULL AUTO_INCREMENT';
            } else {
                if (! $column->nullable) {
                    $def .= ' NOT NULL';
                }
                if ($column->default !== null) {
                    $def .= ' DEFAULT '.$this->formatDefault($column->default);
                }
            }

            $columnDefs[] = $def;
        }

        // Primary key constraint (gunakan yang sudah difilter di atas)
        if (count($pkColumns) > 0) {
            $pkNames = array_map(fn (ColumnDTO $c) => "`{$c->name}`", $pkColumns);
            $columnDefs[] = '  PRIMARY KEY ('.implode(', ', $pkNames).')';
        }

        // Indexes
        foreach ($table->indexes as $index) {
            if ($index->type === 'primary') {
                continue;
            }

            $colNames = array_map(fn (string $c) => "`{$c}`", $index->columns);

            if ($index->unique) {
                $columnDefs[] = "  UNIQUE KEY `{$index->name}` (".implode(', ', $colNames).')';
            } else {
                $columnDefs[] = "  KEY `{$index->name}` (".implode(', ', $colNames).')';
            }
        }

        // Foreign key constraints
        $tableRelations = array_values(array_filter(
            $relations,
            fn (RelationDTO $r) => $r->fromTable === $table->name,
        ));

        foreach ($tableRelations as $rel) {
            $columnDefs[] = "  CONSTRAINT `{$rel->name}` FOREIGN KEY (`{$rel->fromColumn}`) REFERENCES `{$rel->toTable}` (`{$rel->toColumn}`)";
        }

        $lines[] = implode(",\n", $columnDefs);
        $lines[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return implode("\n", $lines).";\n";
    }

    /**
     * Generate DDL for the entire schema — all tables with FK constraints after them.
     */
    public function generateSchemaDDL(array $tables, array $relations, ?string $databaseName = null): string
    {
        $output = '-- AetherDB AI — Schema Export'."\n";
        $output .= '-- Generated: '.now()->toDateTimeString()."\n";
        $output .= '-- Engine: MySQL / MariaDB'."\n\n";

        if ($databaseName) {
            $output .= "CREATE DATABASE IF NOT EXISTS `{$databaseName}`;\n";
            $output .= "USE `{$databaseName}`;\n\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $output .= $this->generateCreateTable($table, $relations);
            $output .= "\n";
        }

        $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        return $output;
    }

    /**
     * Map SchemaParser type names to MySQL DDL types.
     */
    private function mapType(string $type): string
    {
        return self::TYPE_MAP[$type] ?? strtoupper($type);
    }

    /**
     * Format default value for DDL output.
     */
    private function formatDefault(?string $default): string
    {
        if ($default === null) {
            return 'NULL';
        }

        $upper = strtoupper($default);

        // SQL keywords that should NOT be quoted
        if (in_array($upper, ['CURRENT_TIMESTAMP', 'NULL', 'TRUE', 'FALSE'], true)) {
            return $upper;
        }

        // Numeric defaults
        if (is_numeric($default)) {
            return $default;
        }

        return "'{$default}'";
    }
}
