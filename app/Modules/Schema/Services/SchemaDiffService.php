<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Models\SchemaSnapshot;
use App\Modules\Connection\Models\Connection;
use App\Modules\Schema\DTOs\TableDTO;

/**
 * SchemaDiffService — Create schema snapshots and compute diffs between them
 */
class SchemaDiffService
{
    public function __construct(
        private readonly ContextBuilder $contextBuilder,
    ) {}

    /**
     * Create a snapshot of the current schema.
     */
    public function createSnapshot(Connection $connection, string $label): SchemaSnapshot
    {
        $context = $this->contextBuilder->build((string) $connection->id, $connection->database);

        return SchemaSnapshot::create([
            'connection_id' => $connection->id,
            'label' => $label,
            'schema_data' => $context->toArray(),
        ]);
    }

    /**
     * Compute diff between two snapshots.
     */
    public function diff(SchemaSnapshot $a, SchemaSnapshot $b): array
    {
        $oldTables = $this->indexTables($a->schema_data['tables'] ?? []);
        $newTables = $this->indexTables($b->schema_data['tables'] ?? []);

        $new = [];
        $deleted = [];
        $modified = [];

        // Find new and modified tables
        foreach ($newTables as $name => $newTbl) {
            if (! isset($oldTables[$name])) {
                $new[] = $newTbl;
            } else {
                $colDiff = $this->computeColumnDiff($oldTables[$name], $newTbl);
                $indexDiff = $this->computeIndexDiff($oldTables[$name], $newTbl);

                if (! empty($colDiff) || ! empty($indexDiff)) {
                    $modified[] = [
                        'table' => $newTbl->toArray(),
                        'column_changes' => $colDiff,
                        'index_changes' => $indexDiff,
                    ];
                }
            }
        }

        // Find deleted tables (in old but not in new)
        foreach ($oldTables as $name => $oldTbl) {
            if (! isset($newTables[$name])) {
                $deleted[] = $oldTbl;
            }
        }

        return [
            'new_tables' => array_map(fn (TableDTO $t) => $t->toArray(), $new),
            'deleted_tables' => array_map(fn (TableDTO $t) => $t->toArray(), $deleted),
            'modified_tables' => $modified,
            'summary' => [
                'additions' => count($new),
                'deletions' => count($deleted),
                'modifications' => count($modified),
            ],
        ];
    }

    /**
     * Index tables by name for lookup.
     *
     * @param  array  $tables  TableDTO[] or raw arrays
     * @return array<string, TableDTO>
     */
    private function indexTables(array $tables): array
    {
        $indexed = [];
        foreach ($tables as $t) {
            $dto = $t instanceof TableDTO ? $t : TableDTO::fromArray($t);
            $indexed[$dto->name] = $dto;
        }

        return $indexed;
    }

    /**
     * Compute column-level changes between two versions of the same table.
     */
    private function computeColumnDiff(TableDTO $old, TableDTO $new): array
    {
        $oldCols = [];
        foreach ($old->columns as $col) {
            $oldCols[$col->name] = $col;
        }

        $newCols = [];
        foreach ($new->columns as $col) {
            $newCols[$col->name] = $col;
        }

        $changes = [];

        // Added columns
        foreach ($newCols as $name => $col) {
            if (! isset($oldCols[$name])) {
                $changes[] = [
                    'type' => 'added',
                    'column' => $name,
                    'details' => $col->toArray(),
                ];
            }
        }

        // Deleted columns
        foreach ($oldCols as $name => $col) {
            if (! isset($newCols[$name])) {
                $changes[] = [
                    'type' => 'deleted',
                    'column' => $name,
                    'details' => $col->toArray(),
                ];
            }
        }

        // Modified columns
        foreach ($newCols as $name => $col) {
            if (isset($oldCols[$name])) {
                $oldCol = $oldCols[$name];
                $diffs = [];

                if ($col->type !== $oldCol->type) {
                    $diffs[] = ['field' => 'type', 'from' => $oldCol->type, 'to' => $col->type];
                }
                if ($col->nullable !== $oldCol->nullable) {
                    $diffs[] = ['field' => 'nullable', 'from' => $oldCol->nullable, 'to' => $col->nullable];
                }
                if ($col->default !== $oldCol->default) {
                    $diffs[] = ['field' => 'default', 'from' => $oldCol->default, 'to' => $col->default];
                }
                if ($col->primary !== $oldCol->primary) {
                    $diffs[] = ['field' => 'primary', 'from' => $oldCol->primary, 'to' => $col->primary];
                }

                if (! empty($diffs)) {
                    $changes[] = [
                        'type' => 'modified',
                        'column' => $name,
                        'diffs' => $diffs,
                    ];
                }
            }
        }

        return $changes;
    }

    /**
     * Compute index-level changes between two versions of the same table.
     */
    private function computeIndexDiff(TableDTO $old, TableDTO $new): array
    {
        $oldIdx = [];
        foreach ($old->indexes as $idx) {
            $oldIdx[$idx->name] = $idx;
        }

        $newIdx = [];
        foreach ($new->indexes as $idx) {
            $newIdx[$idx->name] = $idx;
        }

        $changes = [];

        foreach ($newIdx as $name => $idx) {
            if (! isset($oldIdx[$name])) {
                $changes[] = ['type' => 'added', 'index' => $name, 'details' => $idx->toArray()];
            }
        }

        foreach ($oldIdx as $name => $idx) {
            if (! isset($newIdx[$name])) {
                $changes[] = ['type' => 'deleted', 'index' => $name, 'details' => $idx->toArray()];
            }
        }

        return $changes;
    }
}
