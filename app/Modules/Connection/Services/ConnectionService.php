<?php

declare(strict_types=1);

namespace App\Modules\Connection\Services;

use App\Modules\Connection\DTOs\ConnectionDTO;
use App\Modules\Connection\Repositories\ConnectionRepository;

class ConnectionService
{
    public function __construct(
        private readonly ConnectionRepository $repository,
        private readonly ConnectionEncryptor $encryptor,
    ) {}

    public function getAll(): array
    {
        return $this->repository->findAll()
            ->map(fn ($c) => ConnectionDTO::fromArray($c->toArray()))
            ->toArray();
    }

    public function getById(string $id): ?ConnectionDTO
    {
        $connection = $this->repository->findById($id);

        if (!$connection) {
            return null;
        }

        return ConnectionDTO::fromArray($connection->toArray());
    }

    public function create(array $data): ConnectionDTO
    {
        $connection = $this->repository->create([
            'name' => $data['name'],
            'driver' => $data['driver'] ?? 'mysql',
            'host' => $data['host'] ?? '127.0.0.1',
            'port' => $data['port'] ?? 3306,
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $this->encryptor->encrypt($data['password'] ?? ''),
            'ssl_enabled' => $data['ssl_enabled'] ?? false,
            'ssh_enabled' => $data['ssh_enabled'] ?? false,
            'ssh_host' => $data['ssh_host'] ?? null,
            'ssh_port' => $data['ssh_port'] ?? 22,
            'ssh_user' => $data['ssh_user'] ?? null,
            'ssh_key' => !empty($data['ssh_key']) ? $this->encryptor->encrypt($data['ssh_key']) : null,
            'status' => 'disconnected',
        ]);

        return ConnectionDTO::fromArray($connection->toArray());
    }

    public function update(string $id, array $data): ?ConnectionDTO
    {
        $connection = $this->repository->findById($id);

        if (!$connection) {
            return null;
        }

        $updateData = [
            'name' => $data['name'] ?? $connection->name,
            'driver' => $data['driver'] ?? $connection->driver,
            'host' => $data['host'] ?? $connection->host,
            'port' => $data['port'] ?? $connection->port,
            'database' => $data['database'] ?? $connection->database,
            'username' => $data['username'] ?? $connection->username,
            'ssl_enabled' => $data['ssl_enabled'] ?? $connection->ssl_enabled,
            'ssh_enabled' => $data['ssh_enabled'] ?? $connection->ssh_enabled,
            'ssh_host' => $data['ssh_host'] ?? $connection->ssh_host,
            'ssh_port' => $data['ssh_port'] ?? $connection->ssh_port,
            'ssh_user' => $data['ssh_user'] ?? $connection->ssh_user,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $this->encryptor->encrypt($data['password']);
        }

        if (!empty($data['ssh_key'])) {
            $updateData['ssh_key'] = $this->encryptor->encrypt($data['ssh_key']);
        }

        $updated = $this->repository->update($id, $updateData);

        return $updated ? ConnectionDTO::fromArray($updated->toArray()) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function test(string $id): array
    {
        $connection = $this->repository->findById($id);

        if (!$connection) {
            return ['success' => false, 'message' => 'Connection not found'];
        }

        try {
            $driverMap = [
                'mysql' => 'pdo_mysql',
                'mariadb' => 'pdo_mysql',
            ];

            $config = [
                'driver' => $driverMap[$connection->driver] ?? 'pdo_mysql',
                'dbname' => $connection->database,
                'user' => $connection->username,
                'password' => $this->encryptor->decrypt($connection->password),
                'charset' => 'utf8mb4',
            ];

            // Use unix_socket for local connections when host is empty
            if (empty($connection->host) || $connection->host === 'localhost' || $connection->host === '127.0.0.1') {
                $socketPath = '/var/run/mysqld/mysqld.sock';
                if (file_exists($socketPath)) {
                    $config['unix_socket'] = $socketPath;
                } else {
                    $config['host'] = $connection->host ?: '127.0.0.1';
                    $config['port'] = (int) ($connection->port ?: 3306);
                }
            } else {
                $config['host'] = $connection->host;
                $config['port'] = (int) ($connection->port ?: 3306);
            }

            if ($connection->ssl_enabled) {
                $config['sslmode'] = 'prefer';
            }

            $conn = \Doctrine\DBAL\DriverManager::getConnection($config);
            $conn->executeQuery('SELECT 1');

            $this->repository->update($id, ['status' => 'connected']);

            return ['success' => true, 'message' => 'Connection successful'];
        } catch (\Throwable $e) {
            $this->repository->update($id, ['status' => 'error']);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
