# Skill: build-graph

## Trigger
Gunakan skill ini ketika mengerjakan fitur Visual Database Graph (Vue Flow).

## Instruksi untuk Agent

### Komponen yang Perlu Dibuat
```
resources/js/components/graph/
├── SchemaGraph.vue        # Root canvas
├── TableNode.vue          # Custom node untuk tabel
├── RelationEdge.vue       # Custom edge untuk FK relation
├── GraphToolbar.vue       # Zoom, layout, filter controls
└── GraphMinimap.vue       # Overview minimap
```

### Struktur Data Graph
```typescript
// Node (satu tabel = satu node)
interface TableNodeData {
  tableName: string
  columns: ColumnDTO[]
  rowCount: number
  sizeKb: number
  cluster: string          // Domain cluster dari AI
  clusterColor: string     // Warna border cluster
  hasWarning: boolean      // Ada AI warning?
}

// Edge (satu FK = satu edge)
interface RelationEdgeData {
  fromTable: string
  toTable: string
  fromColumn: string
  toColumn: string
  relationType: 'one-to-one' | 'one-to-many' | 'many-to-many'
}
```

### Auto Layout dengan Dagre
```typescript
import dagre from '@dagrejs/dagre'

function applyDagreLayout(nodes, edges) {
  const g = new dagre.graphlib.Graph()
  g.setDefaultEdgeLabel(() => ({}))
  g.setGraph({ rankdir: 'LR', nodesep: 80, ranksep: 160, marginx: 50, marginy: 50 })

  nodes.forEach(node => {
    g.setNode(node.id, { width: 280, height: 200 })
  })

  edges.forEach(edge => {
    g.setEdge(edge.source, edge.target)
  })

  dagre.layout(g)

  return nodes.map(node => {
    const pos = g.node(node.id)
    return { ...node, position: { x: pos.x - 140, y: pos.y - 100 } }
  })
}
```

### Design Rules untuk Graph
- Node background: `bg-card` (`hsl(0 0% 7%)`)
- Node border: `border-border` default, cluster color untuk domain highlight
- Node header: tabel name dalam `font-mono font-bold`
- Column list: max 8 kolom ditampilkan, sisanya collapsed
- Edge: SVG path, warna `hsl(0 0% 40%)` default, `hsl(0 0% 98%)` saat hover
- Arrow: directional arrow di ujung edge (menunjukkan arah FK)

### Cluster Colors (AI Domain)
```typescript
const CLUSTER_COLORS = {
  auth:       '#3b82f6', // blue
  commerce:   '#10b981', // emerald
  finance:    '#f59e0b', // amber
  logistics:  '#8b5cf6', // violet
  content:    '#ec4899', // pink
  system:     '#6b7280', // gray (default)
}
```
