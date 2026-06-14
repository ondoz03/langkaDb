<?php

declare(strict_types=1);

namespace App\Modules\Schema\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaParser;
use App\Modules\Schema\Services\SchemaScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchemaController extends Controller
{
    public function __construct(
        private readonly SchemaScanner $scanner,
        private readonly SchemaParser $parser,
        private readonly ContextBuilder $contextBuilder,
    ) {}

    public function schema(string $id): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            return response()->json(['data' => $context->toArray()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function tables(string $id): JsonResponse
    {
        try {
            $schemaManager = $this->scanner->scan($id);
            $tables = $this->parser->parse($schemaManager);

            return response()->json([
                'data' => array_map(fn ($t) => [
                    'name' => $t->name,
                    'columns' => count($t->columns),
                    'indexes' => count($t->indexes),
                ], $tables),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function tableDetail(string $id, string $table): JsonResponse
    {
        try {
            $schemaManager = $this->scanner->scan($id);
            $tables = $this->parser->parse($schemaManager);

            foreach ($tables as $t) {
                if ($t->name === $table) {
                    return response()->json(['data' => $t->toArray()]);
                }
            }

            return response()->json(['message' => 'Table not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function context(string $id): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            return response()->json(['data' => $context->toArray()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function apply(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string',
            'tables' => 'nullable|array',
            'relations' => 'nullable|array',
        ]);

        try {
            $sql = $request->input('sql');
            $tables = $request->input('tables', []);
            $existingTables = $request->input('existing_tables', []);

            // Use the connection's database config
            $connection = config("database.connections.connection_{$id}");

            if (! $connection) {
                // Try to resolve dynamically - get connection name from connections table
                $conn = \App\Modules\Connections\Models\Connection::find($id);
                if (! $conn) {
                    return response()->json(['message' => 'Connection not found'], 404);
                }
                $connection = $conn->config ?? [];
            }

            if (empty($connection)) {
                return response()->json(['message' => 'Connection configuration not found'], 400);
            }

            // Register and connect
            $connName = 'schema_apply_' . $id;
            config(["database.connections.{$connName}" => $connection]);
            $connName = (string) $connName;

            $created = 0;
            $updated = 0;

            // Parse individual statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn ($s) => ! empty($s)
            );

            foreach ($statements as $statement) {
                $upper = strtoupper($statement);
                $isCreate = str_contains($upper, 'CREATE TABLE');
                $tableName = '';

                if ($isCreate) {
                    preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`?(\w+)`?)/i', $statement, $m);
                    $tableName = $m[1] ?? '';

                    // Check if table exists first
                    $exists = DB::connection($connName)
                        ->select("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tableName]);

                    $tableExists = ! empty($exists) && ($exists[0]->cnt ?? 0) > 0;

                    if ($tableExists) {
                        // ALTER TABLE approach - get existing columns
                        $existingCols = DB::connection($connName)
                            ->select("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?", [$tableName]);
                        $existingColNames = array_map(fn ($c) => $c->COLUMN_NAME, $existingCols);

                        // Parse new columns from CREATE statement
                        preg_match('/CREATE\s+TABLE.*?\((.*)\)\s*(?:ENGINE|;|$)/is', $statement, $bodyMatch);
                        if (! empty($bodyMatch[1])) {
                            $body = $bodyMatch[1];
                            // Extract column definitions (ignore constraints/indexes for column detection)
                            preg_match_all('/`?(\w+)`?\s+(\w+(?:\s*\([^)]*\))?(?:\s+UNSIGNED)?(?:\s+ZEROFILL)?)/i', $body, $colMatches);
                            $newColNames = $colMatches[1] ?? [];

                            foreach ($newColNames as $i => $colName) {
                                if (! in_array($colName, $existingColNames)) {
                                    $colDef = $colMatches[0][$i] ?? '';
                                    $alterSql = "ALTER TABLE `{$tableName}` ADD {$colDef}";
                                    DB::connection($connName)->statement($alterSql);
                                    $updated++;
                                }
                            }
                        }
                    } else {
                        // Wrap with IF NOT EXISTS
                        $createSql = preg_replace(
                            '/CREATE\s+TABLE/i',
                            'CREATE TABLE IF NOT EXISTS',
                            $statement
                        );
                        DB::connection($connName)->statement($createSql);
                        $created++;
                    }
                } elseif (str_contains($upper, 'ALTER TABLE') || str_contains($upper, 'CREATE INDEX')) {
                    // Run ALTER/INDEX statements directly (idempotent)
                    try {
                        DB::connection($connName)->statement($statement);
                        $updated++;
                    } catch (\Throwable $alterErr) {
                        Log::warning('Schema apply: ALTER statement skipped', [
                            'error' => $alterErr->getMessage(),
                            'sql' => substr($statement, 0, 200),
                        ]);
                    }
                }
            }

            DB::disconnect($connName);

            return response()->json([
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'message' => "Schema applied: {$created} tables created, {$updated} columns/indexes updated",
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Schema apply failed', [
                'error' => $e->getMessage(),
                'connection_id' => $id,
            ]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
