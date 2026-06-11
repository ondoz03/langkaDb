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

let saveTimer: ReturnType<typeof setTimeout> | null = null

export function useGraph() {
  const schema = ref<SchemaResponse | null>(null)
  const nodes = ref<Node[]>([])
  const edges = ref<Edge[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const hoveredNode = ref<string | null>(null)
  const selectedNode = ref<TableData | null>(null)
  const viewport = ref<ViewportTransform>({ x: 0, y: 0, zoom: 0.6 })
  const searchQuery = ref('')
  const showOnlyConnected = ref(false)
  const saving = ref(false)

  function getFilteredNodes() {
    let result: any[] = [...nodes.value]
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      result = result.filter((n: any) => (n.data as TableData)?.tableName?.toLowerCase().includes(q))
    }
    if (showOnlyConnected.value) {
      const connectedIds = new Set((edges.value as any[]).flatMap((e: any) => [e.source, e.target]))
      result = result.filter((n: any) => connectedIds.has(n.id))
    }
    return result as Node[]
  }

  async function loadPositions(connectionId: string): Promise<{
    positions: Map<string, { x: number; y: number }>
    customTables: TableData[]
  } | null> {
    try {
      const res = await fetch(`/api/connections/${connectionId}/designer/diagrams`)
      if (!res.ok) return null
      const json = await res.json()
      const diagrams = json.data ?? []
      if (diagrams.length === 0) return null
      const latest = diagrams[diagrams.length - 1]
      const posMap = new Map<string, { x: number; y: number }>()
      const customTables: TableData[] = []
      for (const node of latest.nodes ?? []) {
        posMap.set(node.table_name, { x: node.x_pos, y: node.y_pos })
        if (node.metadata?.columns) {
          customTables.push(node.metadata as TableData)
        }
      }
      return { positions: posMap, customTables }
    } catch {
      return null
    }
  }

  function savePositions(connectionId: string, nodeList: any[]) {
    if (saveTimer) clearTimeout(saveTimer)
    saveTimer = setTimeout(async () => {
      saving.value = true
      try {
        const nodesData = nodeList.map(n => ({
          table_name: n.id,
          x_pos: Math.round(n.position.x),
          y_pos: Math.round(n.position.y),
          metadata: (n.data as TableData) ?? null,
        }))

        const listRes = await fetch(`/api/connections/${connectionId}/designer/diagrams`)
        const listJson = await listRes.json()
        const diagrams = listJson.data ?? []
        const diagramId = diagrams.length > 0 ? diagrams[diagrams.length - 1].id : null

        if (diagramId) {
          await fetch(`/api/designer/diagrams/${diagramId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              name: `Diagram - ${new Date().toLocaleDateString()}`,
              connection_id: connectionId,
              nodes: nodesData,
              layout_data: { viewport: { x: 0, y: 0, zoom: 0.6 } },
            }),
          })
        } else {
          await fetch('/api/designer/diagrams', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              name: `Diagram - ${new Date().toLocaleDateString()}`,
              connection_id: connectionId,
              nodes: nodesData,
            }),
          })
        }
      } catch {
        // silent — auto-save is best-effort
      } finally {
        saving.value = false
      }
    }, 2000)
  }

  async function loadSchema(connectionId: string) {
    loading.value = true
    error.value = null
    try {
      const [schemaRes, saved] = await Promise.all([
        fetch(`/api/connections/${connectionId}/schema`),
        loadPositions(connectionId),
      ])
      if (!schemaRes.ok) throw new Error('Failed to load schema')
      const json = await schemaRes.json()
      schema.value = json.data

      // Merge custom tables from saved diagram into schema data
      const data = json.data as SchemaResponse
      const existingNames = new Set(data.tables.map((t: SchemaTable) => t.name))
      const customTables = saved?.customTables ?? []
      for (const ct of customTables) {
        if (!existingNames.has(ct.tableName)) {
          data.tables.push({
            name: ct.tableName,
            columns: ct.columns,
            indexes: ct.indexes ?? [],
            row_count: ct.rowCount,
            size_mb: ct.sizeKb / 1000,
          })
          existingNames.add(ct.tableName)
        }
      }

      buildGraph(data, saved?.positions ?? null)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load schema'
    } finally {
      loading.value = false
    }
  }

  function buildGraph(data: SchemaResponse, savedPositions?: Map<string, { x: number; y: number }> | null) {
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

    if (savedPositions && savedPositions.size > 0) {
      const laidOut = applyDagreLayout(graphNodes, graphEdges)
      const merged = laidOut.map(n => {
        const saved = savedPositions!.get(n.id)
        return saved ? { ...n, position: saved } : n
      })
      nodes.value = merged
    } else {
      nodes.value = applyDagreLayout(graphNodes, graphEdges)
    }
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

    edgeList.forEach((edge) => g.setEdge(edge.source, edge.target))
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

  function rearrange() {
    if (schema.value) buildGraph(schema.value)
  }

  return {
    schema,
    nodes,
    edges,
    getFilteredNodes,
    loading,
    saving,
    error,
    hoveredNode,
    selectedNode,
    viewport,
    searchQuery,
    showOnlyConnected,
    loadSchema,
    buildGraph,
    savePositions,
    onNodeClick,
    closePanel,
    onViewportChange,
    rearrange,
  }
}
