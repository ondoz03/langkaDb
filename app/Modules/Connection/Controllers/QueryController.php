<?php

declare(strict_types=1);

namespace App\Modules\Connection\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connection\Services\ConnectionEncryptor;
use App\Modules\Connection\Repositories\ConnectionRepository;
use Doctrine\DBAL\DriverManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueryController extends Controller
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepo,
        private readonly ConnectionEncryptor $encryptor,
    ) {}

    public function execute(string $id, Request $request): JsonResponse
    {
        $request->validate(['sql' => 'required|string']);

        $connection = $this->connectionRepo->findById($id);

        if (!$connection) {
            return response()->json(['message' => 'Connection not found'], 404);
        }

        $sql = $request->input('sql');

        $driverMap = [
            'mysql' => 'pdo_mysql',
            'mariadb' => 'pdo_mysql',
        ];

        $config = [
            'driver' => $driverMap[$connection->driver] ?? 'pdo_mysql',
            'host' => $connection->host,
            'port' => (int) $connection->port,
            'dbname' => $connection->database,
            'user' => $connection->username,
            'password' => $this->encryptor->decrypt($connection->password),
            'charset' => 'utf8mb4',
        ];

        try {
            $conn = DriverManager::getConnection($config);
            $stmt = $conn->executeQuery($sql);
            $rows = $stmt->fetchAllAssociative();
            $columns = !empty($rows) ? array_keys($rows[0]) : [];

            return response()->json([
                'data' => [
                    'columns' => $columns,
                    'rows' => $rows,
                    'count' => count($rows),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
