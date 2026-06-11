<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Connection\Repositories\ConnectionRepository;
use App\Modules\Connection\Services\ConnectionEncryptor;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class SchemaScanner
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepo,
        private readonly ConnectionEncryptor $encryptor,
    ) {}

    public function scan(string $connectionId): AbstractSchemaManager
    {
        $connection = $this->connectionRepo->findById($connectionId);

        if (! $connection) {
            throw new \RuntimeException('Connection not found');
        }

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

        // Use unix_socket for local connections
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

        $conn = DriverManager::getConnection($config);

        return $conn->createSchemaManager();
    }
}
