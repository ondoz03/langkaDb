<?php

declare(strict_types=1);

namespace App\Modules\Connection\DTOs;

use Illuminate\Contracts\Support\Arrayable;

readonly class ConnectionDTO implements Arrayable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public bool $sslEnabled,
        public bool $sshEnabled,
        public ?string $sshHost = null,
        public ?int $sshPort = null,
        public ?string $sshUser = null,
        public string $status = 'disconnected',
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            driver: $data['driver'],
            host: $data['host'],
            port: (int) $data['port'],
            database: $data['database'],
            username: $data['username'],
            sslEnabled: (bool) ($data['ssl_enabled'] ?? false),
            sshEnabled: (bool) ($data['ssh_enabled'] ?? false),
            sshHost: $data['ssh_host'] ?? null,
            sshPort: isset($data['ssh_port']) ? (int) $data['ssh_port'] : null,
            sshUser: $data['ssh_user'] ?? null,
            status: $data['status'] ?? 'disconnected',
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'ssl_enabled' => $this->sslEnabled,
            'ssh_enabled' => $this->sshEnabled,
            'ssh_host' => $this->sshHost,
            'ssh_port' => $this->sshPort,
            'ssh_user' => $this->sshUser,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
