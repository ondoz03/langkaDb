<?php

declare(strict_types=1);

namespace App\Modules\Schema\Services;

use App\Modules\Connection\Services\ConnectionEncryptor;
use App\Modules\Connection\Repositories\ConnectionRepository;
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

        if (!$connection) {
            throw new \RuntimeException('Connection not found');
        }

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

        $conn = DriverManager::getConnection($config);

        return $conn->createSchemaManager();
    }
}
