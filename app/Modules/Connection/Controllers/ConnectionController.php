<?php

declare(strict_types=1);

namespace App\Modules\Connection\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connection\Services\ConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectionController extends Controller
{
    public function __construct(
        private readonly ConnectionService $connectionService,
    ) {}

    public function index(): JsonResponse
    {
        $connections = $this->connectionService->getAll();

        return response()
            ->json(['data' => $connections])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function show(string $id): JsonResponse
    {
        $connection = $this->connectionService->getById($id);

        if (! $connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(['data' => $connection->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'driver' => 'required|in:mysql,mariadb',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'ssl_enabled' => 'boolean',
            'ssh_enabled' => 'boolean',
            'ssh_host' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_user' => 'nullable|string|max:255',
            'ssh_key' => 'nullable|string',
        ]);

        $connection = $this->connectionService->create($validated);

        return response()->json(['data' => $connection->toArray()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'driver' => 'sometimes|in:mysql,mariadb',
            'host' => 'sometimes|string|max:255',
            'port' => 'sometimes|integer|min:1|max:65535',
            'database' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255',
            'password' => 'nullable|string',
            'ssl_enabled' => 'boolean',
            'ssh_enabled' => 'boolean',
            'ssh_host' => 'nullable|string|max:255',
            'ssh_port' => 'nullable|integer|min:1|max:65535',
            'ssh_user' => 'nullable|string|max:255',
            'ssh_key' => 'nullable|string',
        ]);

        $connection = $this->connectionService->update($id, $validated);

        if (! $connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(['data' => $connection->toArray()]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->connectionService->delete($id);

        if (! $deleted) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        return response()->json(['message' => 'Connection deleted']);
    }

    public function test(string $id): JsonResponse
    {
        $result = $this->connectionService->test($id);

        return response()->json($result);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => 'required|in:mysql,mariadb',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string',
            'ssl_enabled' => 'boolean',
        ]);

        $result = $this->connectionService->testWithConfig($validated);

        return response()->json($result);
    }
}
