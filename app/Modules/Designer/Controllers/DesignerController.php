<?php

declare(strict_types=1);

namespace App\Modules\Designer\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Designer\Services\DesignerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignerController extends Controller
{
    public function __construct(
        private readonly DesignerService $designerService,
    ) {}

    public function index(): JsonResponse
    {
        $diagrams = $this->designerService->getAll();

        return response()->json(['data' => $diagrams]);
    }

    public function show(string $id): JsonResponse
    {
        $diagram = $this->designerService->getById($id);
        if (! $diagram) {
            return response()->json(['message' => 'Diagram not found'], 404);
        }

        return response()->json(['data' => $diagram->toArray()]);
    }

    public function byConnection(string $connectionId): JsonResponse
    {
        $diagrams = $this->designerService->getByConnection($connectionId);

        return response()->json(['data' => $diagrams]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'connection_id' => 'nullable|string',
            'layout_data' => 'nullable|array',
            'nodes' => 'nullable|array',
            'nodes.*.table_name' => 'required|string',
            'nodes.*.x_pos' => 'numeric',
            'nodes.*.y_pos' => 'numeric',
            'nodes.*.metadata' => 'nullable|array',
            'relations' => 'nullable|array',
            'relations.*.from_table' => 'required|string',
            'relations.*.from_column' => 'required|string',
            'relations.*.to_table' => 'required|string',
            'relations.*.to_column' => 'required|string',
            'relations.*.type' => 'nullable|string',
            'relations.*.name' => 'nullable|string',
        ]);

        $diagram = $this->designerService->create($validated);

        return response()->json(['data' => $diagram->toArray()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'connection_id' => 'nullable|string',
            'layout_data' => 'nullable|array',
            'nodes' => 'nullable|array',
            'nodes.*.table_name' => 'required_with:nodes|string',
            'nodes.*.x_pos' => 'numeric',
            'nodes.*.y_pos' => 'numeric',
            'nodes.*.metadata' => 'nullable|array',
            'relations' => 'nullable|array',
            'relations.*.from_table' => 'required|string',
            'relations.*.from_column' => 'required|string',
            'relations.*.to_table' => 'required|string',
            'relations.*.to_column' => 'required|string',
            'relations.*.type' => 'nullable|string',
            'relations.*.name' => 'nullable|string',
        ]);

        $diagram = $this->designerService->update($id, $validated);
        if (! $diagram) {
            return response()->json(['message' => 'Diagram not found'], 404);
        }

        return response()->json(['data' => $diagram->toArray()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->designerService->delete($id);
        if (! $deleted) {
            return response()->json(['message' => 'Diagram not found'], 404);
        }

        return response()->json(['message' => 'Diagram deleted']);
    }

    public function saveLayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'diagram_id' => 'required|string|exists:diagrams,id',
            'nodes' => 'required|array',
            'nodes.*.table_name' => 'required|string',
            'nodes.*.x_pos' => 'required|numeric',
            'nodes.*.y_pos' => 'required|numeric',
            'nodes.*.metadata' => 'nullable|array',
        ]);

        $this->designerService->update($validated['diagram_id'], [
            'nodes' => $validated['nodes'],
        ]);

        return response()->json(['message' => 'Layout saved']);
    }
}
