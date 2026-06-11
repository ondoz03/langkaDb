<?php

declare(strict_types=1);

namespace App\Modules\Designer\Services;

use App\Modules\Designer\DTOs\DiagramDTO;
use App\Modules\Designer\Repositories\DiagramRepository;

class DesignerService
{
    public function __construct(
        private readonly DiagramRepository $repository,
    ) {}

    public function getAll(): array
    {
        return $this->repository->findAll()
            ->map(fn ($d) => DiagramDTO::fromModel($d))
            ->toArray();
    }

    public function getById(string $id): ?DiagramDTO
    {
        $diagram = $this->repository->findById($id);

        return $diagram ? DiagramDTO::fromModel($diagram) : null;
    }

    public function getByConnection(string $connectionId): array
    {
        return $this->repository->findByConnection($connectionId)
            ->map(fn ($d) => DiagramDTO::fromModel($d))
            ->toArray();
    }

    public function create(array $data): DiagramDTO
    {
        $nodes = $data['nodes'] ?? [];
        unset($data['nodes']);

        $diagram = $this->repository->create($data);

        if (! empty($nodes)) {
            $this->repository->saveNodes($diagram->id, $nodes);
        }

        return DiagramDTO::fromModel($diagram->load('nodes'));
    }

    public function update(string $id, array $data): ?DiagramDTO
    {
        $nodes = $data['nodes'] ?? null;
        unset($data['nodes']);

        $diagram = $this->repository->update($id, $data);
        if (! $diagram) {
            return null;
        }

        if ($nodes !== null) {
            $this->repository->saveNodes($id, $nodes);
        }

        return DiagramDTO::fromModel($diagram->fresh()->load('nodes'));
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
