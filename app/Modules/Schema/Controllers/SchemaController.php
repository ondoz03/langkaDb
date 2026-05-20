<?php

declare(strict_types=1);

namespace App\Modules\Schema\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Schema\Services\ContextBuilder;
use App\Modules\Schema\Services\SchemaParser;
use App\Modules\Schema\Services\SchemaScanner;
use Illuminate\Http\JsonResponse;

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
}
