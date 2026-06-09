<?php

declare(strict_types=1);

namespace App\Modules\Schema\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Schema\Services\SqlImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ImportController — Import SQL DDL and convert to schema
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly SqlImportService $importService,
    ) {}

    /**
     * Parse SQL DDL and return tables + relations for graph rendering.
     */
    public function importSql(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string|min:10',
        ]);

        try {
            $result = $this->importService->parse($request->input('sql'));

            $tables = [];
            foreach ($result['tables'] as $table) {
                $tables[] = $table->toArray();
            }

            $relations = [];
            foreach ($result['relations'] as $relation) {
                $relations[] = $relation->toArray();
            }

            return response()->json([
                'data' => [
                    'tables' => $tables,
                    'relations' => $relations,
                    'summary' => [
                        'total_tables' => count($tables),
                        'total_relations' => count($relations),
                        'total_indexes' => array_sum(array_map(fn ($t) => count($t['indexes'] ?? []), $tables)),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to parse SQL: ' . $e->getMessage()], 422);
        }
    }
}
