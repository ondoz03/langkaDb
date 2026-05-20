<?php

declare(strict_types=1);

namespace App\Modules\Connection\Repositories;

use App\Modules\Connection\Models\Connection;
use Illuminate\Database\Eloquent\Collection;

class ConnectionRepository
{
    public function findAll(): Collection
    {
        return Connection::orderBy('created_at', 'desc')->get();
    }

    public function findById(string $id): ?Connection
    {
        return Connection::find($id);
    }

    public function create(array $data): Connection
    {
        return Connection::create($data);
    }

    public function update(string $id, array $data): ?Connection
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return null;
        }

        $connection->update($data);
        $connection->refresh();

        return $connection;
    }

    public function delete(string $id): bool
    {
        $connection = Connection::find($id);

        if (!$connection) {
            return false;
        }

        return (bool) $connection->delete();
    }
}
