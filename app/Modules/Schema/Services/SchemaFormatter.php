<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Schema\DTOs\SchemaContextDTO;

class SchemaFormatter
{
    public function compact(SchemaContextDTO $context): string
    {
        $lines = [];

        foreach ($context->tables as $table) {
            $cols = [];

            foreach ($table->columns as $col) {
                $parts = [$col->name];
                $parts[] = $col->type;

                if ($col->primary) {
                    $parts[] = 'PK';
                }

                if ($col->nullable) {
                    $parts[] = 'NULL';
                }

                $cols[] = implode(' ', $parts);
            }

            $lines[] = $table->name . ': ' . implode(', ', $cols);
        }

        $relations = [];

        foreach ($context->relations as $rel) {
            $relations[] = "{$rel->fromTable}.{$rel->fromColumn} → {$rel->toTable}.{$rel->toColumn}";
        }

        $result = implode("\n", $lines);

        if ($relations) {
            $result .= "\n\nRelations:\n" . implode("\n", $relations);
        }

        return $result;
    }

    public function summary(SchemaContextDTO $context): string
    {
        $totalCols = 0;
        $lines = [];

        foreach ($context->tables as $table) {
            $colCount = count($table->columns);
            $totalCols += $colCount;
            $pk = [];

            foreach ($table->columns as $col) {
                if ($col->primary) {
                    $pk[] = $col->name;
                }
            }

            $info = "{$table->name} ({$colCount} cols)";

            if ($pk) {
                $info .= ' PK: ' . implode(', ', $pk);
            }

            $lines[] = $info;
        }

        $result = implode("\n", $lines);
        $result .= "\n\nTotal: " . count($context->tables) . " tables, {$totalCols} columns";

        $relations = [];

        foreach ($context->relations as $rel) {
            $relations[] = "{$rel->fromTable}.{$rel->fromColumn} → {$rel->toTable}.{$rel->toColumn}";
        }

        if ($relations) {
            $result .= ", " . count($relations) . " relations\n" . implode("\n", $relations);
        }

        return $result;
    }
}
