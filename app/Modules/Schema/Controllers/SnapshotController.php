<?php

declare(strict_types=1);

namespace App\Modules\Schema\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SchemaSnapshot;
use App\Modules\Connection\Models\Connection;
use App\Modules\Schema\Services\SchemaDiffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SnapshotController — Manage schema snapshots and compute diffs
 */
class SnapshotController extends Controller
{
    public function __construct(
        private readonly SchemaDiffService $diffService,
    ) {}

    /**
     * List all snapshots for a connection.
     */
    public function index(string $connectionId): JsonResponse
    {
        $snapshots = SchemaSnapshot::where('connection_id', $connectionId)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'label', 'created_at']);

        return response()->json(['data' => $snapshots]);
    }

    /**
     * Create a new snapshot from the current schema.
     */
    public function store(Request $request, string $connectionId): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $connection = Connection::findOrFail($connectionId);

        $snapshot = $this->diffService->createSnapshot($connection, $request->input('label'));

        return response()->json(['data' => [
            'id' => $snapshot->id,
            'label' => $snapshot->label,
            'created_at' => $snapshot->created_at,
        ]], 201);
    }

    /**
     * Show a single snapshot's data.
     */
    public function show(string $id): JsonResponse
    {
        $snapshot = SchemaSnapshot::findOrFail($id);

        return response()->json(['data' => $snapshot->schema_data]);
    }

    /**
     * Delete a snapshot.
     */
    public function destroy(string $id): JsonResponse
    {
        SchemaSnapshot::findOrFail($id)->delete();

        return response()->json(['message' => 'Snapshot deleted']);
    }

    /**
     * Compute diff between two snapshots.
     */
    public function diff(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'compare_with' => 'required|integer|exists:schema_snapshots,id',
        ]);

        $snapshotA = SchemaSnapshot::findOrFail((int) $request->input('compare_with'));
        $snapshotB = SchemaSnapshot::findOrFail((int) $id);

        $result = $this->diffService->diff($snapshotA, $snapshotB);

        return response()->json(['data' => $result]);
    }
}
