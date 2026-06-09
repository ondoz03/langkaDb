<?php

declare(strict_types=1);

namespace App\Modules\Schema\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SqlExportService;
use Illuminate\Http\JsonResponse;

/**
 * ExportController — Export schema as SQL DDL or other formats
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ContextBuilder $contextBuilder,
        private readonly SqlExportService $exportService,
    ) {}

    /**
     * Export entire schema as SQL DDL.
     */
    public function exportSql(string $id): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            $sql = $this->exportService->generateSchemaDDL(
                $context->tables,
                $context->relations,
                $context->database,
            );

            return response()->json([
                'data' => [
                    'sql' => $sql,
                    'filename' => $context->database ? "{$context->database}.sql" : 'schema.sql',
                    'database' => $context->database,
                    'table_count' => count($context->tables),
                    'relation_count' => count($context->relations),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export a single table as SQL DDL.
     */
    public function exportTableSql(string $id, string $table): JsonResponse
    {
        try {
            $context = $this->contextBuilder->build($id, 'database');

            $match = null;
            foreach ($context->tables as $t) {
                if ($t->name === $table) {
                    $match = $t;
                    break;
                }
            }

            if ($match === null) {
                return response()->json(['message' => 'Table not found'], 404);
            }

            $sql = $this->exportService->generateCreateTable($match, $context->relations);

            return response()->json([
                'data' => [
                    'sql' => $sql,
                    'filename' => "{$match->name}.sql",
                    'table' => $match->name,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
