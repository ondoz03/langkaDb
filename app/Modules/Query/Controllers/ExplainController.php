<?php

declare(strict_types=1);

namespace App\Modules\Query\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Query\Services\ExplainAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExplainController extends Controller
{
    public function __construct(
        private readonly ExplainAnalyzer $analyzer,
    ) {}

    /**
     * Analyze a query via EXPLAIN FORMAT=JSON.
     */
    public function analyze(string $id, Request $request): JsonResponse
    {
        $request->validate(['sql' => 'required|string']);

        $sql = $request->input('sql');

        try {
            $result = $this->analyzer->analyze($sql, $id);

            return response()->json(['data' => $result->toArray()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
