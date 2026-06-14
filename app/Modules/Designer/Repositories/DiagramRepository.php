<?php

declare(strict_types=1);

namespace App\Modules\Designer\Repositories;

use App\Modules\Designer\Models\Diagram;
use App\Modules\Designer\Models\DiagramNode;
use App\Modules\Designer\Models\DiagramRelation;
use Illuminate\Database\Eloquent\Collection;

class DiagramRepository
{
    public function findAll(): Collection
    {
        return Diagram::with('nodes', 'relations')->orderBy('created_at', 'desc')->get();
    }

    public function findById(string $id): ?Diagram
    {
        return Diagram::with('nodes', 'relations')->find($id);
    }

    public function findByConnection(string $connectionId): Collection
    {
        return Diagram::with('nodes', 'relations')
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

        return $diagram->load('nodes', 'relations');
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

    public function saveRelations(string $diagramId, array $relations): void
    {
        DiagramRelation::where('diagram_id', $diagramId)->delete();

        foreach ($relations as $rel) {
            DiagramRelation::create([
                'diagram_id' => $diagramId,
                'from_table' => $rel['from_table'],
                'from_column' => $rel['from_column'],
                'to_table' => $rel['to_table'],
                'to_column' => $rel['to_column'],
                'type' => $rel['type'] ?? 'belongs_to',
                'name' => $rel['name'] ?? null,
            ]);
        }
    }
}
