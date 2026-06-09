<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\ColumnDTO;
use App\Modules\Schema\DTOs\IndexDTO;
use App\Modules\Schema\DTOs\RelationDTO;
use App\Modules\Schema\DTOs\TableDTO;
use PHPSQLParser\PHPSQLParser;

/**
 * SqlImportService — Parse SQL DDL (CREATE TABLE) into TableDTO[] + RelationDTO[]
 */
class SqlImportService
{
    private const TYPE_MAP_REVERSE = [
        'INT' => 'Integer',
        'INTEGER' => 'Integer',
        'BIGINT' => 'BigInt',
        'SMALLINT' => 'SmallInt',
        'TINYINT' => 'TinyInt',
        'FLOAT' => 'Float',
        'DOUBLE' => 'Double',
        'DECIMAL' => 'Decimal',
        'NUMERIC' => 'Decimal',
        'VARCHAR' => 'String',
        'CHAR' => 'String',
        'TEXT' => 'Text',
        'TINYTEXT' => 'Text',
        'MEDIUMTEXT' => 'Text',
        'LONGTEXT' => 'Text',
        'BOOLEAN' => 'Boolean',
        'DATETIME' => 'DateTime',
        'DATE' => 'Date',
        'TIME' => 'Time',
        'TIMESTAMP' => 'Timestamp',
        'YEAR' => 'Year',
        'BINARY' => 'Binary',
        'BLOB' => 'Blob',
        'JSON' => 'Json',
    ];

    /**
     * Parse SQL DDL string into an array of TableDTO and RelationDTO.
     *
     * @return array{tables: TableDTO[], relations: RelationDTO[]}
     */
    public function parse(string $sql): array
    {
        $tables = [];
        $relations = [];

        // Pattern to extract individual CREATE TABLE statements
        $pattern = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`?(\w+)`?\s*)?\((.*?)\)\s*(?:ENGINE\s*=\s*\w+)?\s*(?:DEFAULT\s+CHARSET\s*=\s*\w+)?\s*(?:COLLATE\s*=\s*\w+)?\s*;/sim';

        preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tableName = $match[1];
            $body = $match[2];

            $parsed = $this->parseCreateTableBody($tableName, $body);
            if ($parsed !== null) {
                $tables[] = $parsed['table'];
                $relations = array_merge($relations, $parsed['relations']);
            }
        }

        // If regex failed, try PHPSQLParser as fallback
        if (empty($tables) && class_exists(PHPSQLParser::class)) {
            return $this->parseWithPHPParser($sql);
        }

        return ['tables' => $tables, 'relations' => $relations];
    }

    /**
     * Parse a single CREATE TABLE body (between parentheses).
     */
    private function parseCreateTableBody(string $tableName, string $body): ?array
    {
        $columns = [];
        $indexes = [];
        $relations = [];
        $primaryColumns = [];

        // Split by comma, respecting nested parentheses
        $parts = $this->splitByCommaOutsideParens($body);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            $upper = strtoupper($part);

            // Constraint / Foreign Key
            if (preg_match('/^\s*(?:CONSTRAINT\s+`?\w+`?\s+)?FOREIGN\s+KEY\s*\(`?(\w+)`?\)\s*REFERENCES\s+`?(\w+)`?\s*\(`?(\w+)`?\)/i', $part, $m)) {
                $fkName = 'fk_' . $tableName . '_' . $m[1];
                $relations[] = new RelationDTO(
                    name: $fkName,
                    fromTable: $tableName,
                    fromColumn: $m[1],
                    toTable: $m[2],
                    toColumn: $m[3],
                    type: 'belongs_to',
                );
                continue;
            }

            // PRIMARY KEY (col1, col2)
            if (preg_match('/^\s*(?:PRIMARY\s+KEY|INDEX|UNIQUE)\s*(?:`?\w+`?)?\s*\(([^)]+)\)/i', $part, $m)) {
                $pkCols = $this->parseColumnList($m[1]);
                $primaryColumns = array_merge($primaryColumns, $pkCols);

                // Also add as index
                $indexName = 'PRIMARY';
                $indexes[] = new IndexDTO(
                    name: $indexName,
                    columns: $pkCols,
                    unique: true,
                    type: 'primary',
                );
                continue;
            }

            // UNIQUE KEY / UNIQUE INDEX
            if (preg_match('/^\s*UNIQUE\s+(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]+)\)/i', $part, $m)) {
                $indexes[] = new IndexDTO(
                    name: $m[1],
                    columns: $this->parseColumnList($m[2]),
                    unique: true,
                    type: 'unique',
                );
                continue;
            }

            // KEY / INDEX
            if (preg_match('/^\s*(?:KEY|INDEX)\s+`?(\w+)`?\s*\(([^)]+)\)/i', $part, $m)) {
                $indexes[] = new IndexDTO(
                    name: $m[1],
                    columns: $this->parseColumnList($m[2]),
                    unique: false,
                    type: 'index',
                );
                continue;
            }

            // Column definition
            $column = $this->parseColumnDefinition($part, $primaryColumns);
            if ($column !== null) {
                $columns[] = $column;
            }
        }

        if (empty($columns)) {
            return null;
        }

        $table = new TableDTO(
            name: $tableName,
            columns: $columns,
            indexes: $indexes,
            rowCount: 0,
            sizeMb: 0.0,
            comment: null,
        );

        return ['table' => $table, 'relations' => $relations];
    }

    /**
     * Parse a single column definition line.
     */
    private function parseColumnDefinition(string $part, array $primaryColumns): ?ColumnDTO
    {
        // Match: column_name TYPE[(params)] [NOT NULL|NULL] [DEFAULT value] [AUTO_INCREMENT] [COMMENT '...']
        if (!preg_match('/^`?(\w+)`?\s+(\w+(?:\s*\([^)]*\))?(?:\s+UNSIGNED)?(?:\s+ZEROFILL)?)(.*)$/i', $part, $m)) {
            return null;
        }

        $colName = $m[1];
        $rawType = $m[2];
        $suffix = strtoupper(trim($m[3]));

        $type = $this->normalizeType($rawType);
        $nullable = !str_contains($suffix, 'NOT NULL') && !str_contains($suffix, 'PRIMARY KEY');
        $isPrimary = in_array($colName, $primaryColumns, true) || str_contains($suffix, 'PRIMARY KEY');

        $default = null;
        if (preg_match('/DEFAULT\s+(\S+)/i', $part, $dm)) {
            $default = trim($dm[1], "'\"");
        }

        // Handle special keywords
        if (preg_match('/DEFAULT\s+(CURRENT_TIMESTAMP|NULL|TRUE|FALSE)\b/i', $part, $dm)) {
            $default = strtoupper($dm[1]);
        }

        $comment = null;
        if (preg_match("/COMMENT\s+'([^']*)'/i", $part, $cm)) {
            $comment = $cm[1];
        }

        return new ColumnDTO(
            name: $colName,
            type: $type,
            nullable: $nullable && !$isPrimary,
            default: $default,
            primary: $isPrimary,
            comment: $comment,
        );
    }

    /**
     * Normalize SQL type to SchemaParser-compatible type name.
     */
    private function normalizeType(string $rawType): string
    {
        // Strip length and modifiers
        $base = preg_replace('/\(.*\)/', '', $rawType);
        $base = trim(strtoupper(preg_replace('/\s+(UNSIGNED|ZEROFILL)/', '', $base)));

        // Handle special cases
        if ($base === 'TINYINT' && str_contains(strtoupper($rawType), '(1)')) {
            return 'Boolean';
        }

        return self::TYPE_MAP_REVERSE[$base] ?? $base;
    }

    /**
     * Split body by top-level commas (not inside parentheses).
     */
    private function splitByCommaOutsideParens(string $body): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $len = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '(' || $ch === '[') {
                $depth++;
                $current .= $ch;
            } elseif ($ch === ')' || $ch === ']') {
                $depth--;
                $current .= $ch;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
            } else {
                $current .= $ch;
            }
        }

        if (trim($current) !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    /**
     * Parse column list like `col1, col2, col3` into array.
     */
    private function parseColumnList(string $list): array
    {
        $columns = [];
        foreach (explode(',', $list) as $col) {
            $columns[] = trim($col, " `'\"");
        }
        return array_filter($columns);
    }

    /**
     * Fallback: try PHPSQLParser for complex DDL.
     */
    private function parseWithPHPParser(string $sql): array
    {
        $tables = [];
        $relations = [];

        try {
            $parser = new PHPSQLParser($sql);

            // PHPSQLParser's CREATE TABLE parsing is limited;
            // fall through — the regex parser should handle most cases.
        } catch (\Throwable) {
            // Silently fail, return empty
        }

        return ['tables' => $tables, 'relations' => $relations];
    }
}
