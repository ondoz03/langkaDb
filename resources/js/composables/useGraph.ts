import dagre from '@dagrejs/dagre'
import type { Node, Edge, ViewportTransform } from '@vue-flow/core'
import { ref } from 'vue'

interface ColumnData {
  name: string
  type: string
  nullable: boolean
  default: string | null
  primary: boolean
  comment: string | null
}

interface IndexData {
  name: string
  columns: string[]
  unique: boolean
  type: string
}

export interface TableData {
  tableName: string
  columns: ColumnData[]
  indexes?: IndexData[]
  rowCount: number
  sizeKb: number
}

interface SchemaTable {
  name: string
  columns: ColumnData[]
  indexes?: IndexData[]
  row_count: number
  size_mb: number
}

interface SchemaRelation {
  name: string
  from_table: string
  from_column: string
  to_table: string
  to_column: string
  type: string
}

interface SchemaResponse {
  database: string
  tables: SchemaTable[]
  relations: SchemaRelation[]
  summary: { total_tables: number; total_relations: number; total_indexes: number }
}

export function useGraph() {
  const schema = ref<SchemaResponse | null>(null)
  const nodes = ref<Node[]>([])
  const edges = ref<Edge[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const hoveredNode = ref<string | null>(null)
  const selectedNode = ref<TableData | null>(null)
  const viewport = ref<ViewportTransform>({ x: 0, y: 0, zoom: 0.6 })

  async function loadSchema(connectionId: string) {
    loading.value = true
    error.value = null

    try {
      const res = await fetch(`/api/connections/${connectionId}/schema`)

      if (!res.ok) {
        throw new Error('Failed to load schema')
      }

      const json = await res.json()
      schema.value = json.data
      buildGraph(json.data)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load schema'
    } finally {
      loading.value = false
    }
  }

  function buildGraph(data: SchemaResponse) {
    const graphNodes: Node[] = data.tables.map((table) => ({
      id: table.name,
      type: 'table',
      position: { x: 0, y: 0 },
      data: { tableName: table.name, columns: table.columns, indexes: table.indexes, rowCount: table.row_count, sizeKb: table.size_mb * 1000 } as TableData,
    }))

    const graphEdges: Edge[] = data.relations.map((rel) => ({
      id: `${rel.from_table}.${rel.from_column}_to_${rel.to_table}.${rel.to_column}`,
      source: rel.from_table,
      target: rel.to_table,
      sourceHandle: `${rel.from_table}.${rel.from_column}`,
      targetHandle: `${rel.to_table}.${rel.to_column}`,
      type: 'relation',
      data: { fromColumn: rel.from_column, toColumn: rel.to_column },
    }))

    const laidOutNodes = applyDagreLayout(graphNodes, graphEdges)

    nodes.value = laidOutNodes
    edges.value = graphEdges
  }

  function applyDagreLayout(nodeList: Node[], edgeList: Edge[]): Node[] {
    const g = new dagre.graphlib.Graph()
    g.setDefaultEdgeLabel(() => ({}))
    g.setGraph({ rankdir: 'TB', nodesep: 30, ranksep: 60, marginx: 20, marginy: 20 })

    nodeList.forEach((node) => {
      const d = node.data as TableData
      const cols = d?.columns ?? []
      const colCount = cols.length || 1
      const maxColName = cols.reduce((a: string, c: ColumnData) => (c.name.length > a.length ? c.name : a), '') ?? ''
      const width = Math.max(240, Math.min(400, maxColName.length * 8 + 100))
      const height = Math.max(80, 36 + colCount * 26)

      g.setNode(node.id, { width, height })
    })

    edgeList.forEach((edge) => {
      g.setEdge(edge.source, edge.target)
    })

    dagre.layout(g)

    return nodeList.map((node) => {
      const pos = g.node(node.id)

      return { ...node, position: { x: pos.x - 140, y: pos.y - 100 } }
    })
  }

  function onNodeClick(node: Node) {
    selectedNode.value = node.data as TableData
  }

  function closePanel() {
    selectedNode.value = null
  }

  function onViewportChange(vp: ViewportTransform) {
    viewport.value = vp
  }

  return {
    schema,
    nodes,
    edges,
    loading,
    error,
    hoveredNode,
    selectedNode,
    viewport,
    loadSchema,
    onNodeClick,
    closePanel,
    onViewportChange,
  }
}
