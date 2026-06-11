<?php

declare(strict_types=1);

namespace App\Modules\Designer\Repositories;

use App\Modules\Designer\Models\Diagram;
use App\Modules\Designer\Models\DiagramNode;
use Illuminate\Database\Eloquent\Collection;

class DiagramRepository
{
    public function findAll(): Collection
    {
        return Diagram::with('nodes')->orderBy('created_at', 'desc')->get();
    }

    public function findById(string $id): ?Diagram
    {
        return Diagram::with('nodes')->find($id);
    }

    public function findByConnection(string $connectionId): Collection
    {
        return Diagram::with('nodes')
            ->where('connection_id', $connectionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): Diagram
    {
        return Diagram::create($data);
    }

    public function update(string $id, array $data): ?Diagram
    {
        $diagram = Diagram::find($id);
        if (! $diagram) {
            return null;
        }

        $diagram->update($data);

        return $diagram->load('nodes');
    }

    public function delete(string $id): bool
    {
        return Diagram::destroy($id) > 0;
    }

    public function saveNodes(string $diagramId, array $nodes): void
    {
        DiagramNode::where('diagram_id', $diagramId)->delete();

        foreach ($nodes as $node) {
            DiagramNode::create([
                'diagram_id' => $diagramId,
                'table_name' => $node['table_name'],
                'x_pos' => $node['x_pos'] ?? 0,
                'y_pos' => $node['y_pos'] ?? 0,
                'metadata' => $node['metadata'] ?? null,
            ]);
        }
    }
}
