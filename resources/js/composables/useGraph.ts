import dagre from '@dagrejs/dagre'
import type { Node, Edge, ViewportTransform } from '@vue-flow/core'
import { ref, computed, triggerRef } from 'vue'

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
  foreignKeys?: ForeignKeyData[]
  rowCount: number
  sizeKb: number
}

interface ForeignKeyData {
  column: string
  referencesTable: string
  referencesColumn: string
  onDelete?: string
}

interface DiagramRelationData {
  from_table: string
  from_column: string
  to_table: string
  to_column: string
  type: string
  name?: string | null
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
  const loading = ref(false)
  const error = ref<string | null>(null)
  const hoveredNode = ref<string | null>(null)
  const selectedNode = ref<TableData | null>(null)
  const viewport = ref<ViewportTransform>({ x: 0, y: 0, zoom: 0.6 })
  const searchQuery = ref('')
  const showOnlyConnected = ref(false)
  const saving = ref(false)
  const relations = ref<DiagramRelationData[]>([])

  // Edges are computed dynamically from relations + node positions
  // so that .left/.right handle suffixes match relative table positions
  const edges = computed<Edge[]>(() => {
    const allRelations = [
      ...(schema.value?.relations ?? []),
      ...relations.value,
    ]

    // Deduplicate
    const seen = new Set<string>()
    const uniqueRels = allRelations.filter(r => {
      const key = `${r.from_table}.${r.from_column}->${r.to_table}.${r.to_column}`
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })

    // Map node X positions
    const posMap = new Map<string, number>()
    for (const node of nodes.value) {
      posMap.set(node.id, node.position.x)
    }

    return uniqueRels.map((rel) => {
      const sourceX = posMap.get(rel.from_table) ?? 0
      const targetX = posMap.get(rel.to_table) ?? 0

      // Source is left of target → source.right → target.left
      // Source is right of target → source.left → target.right
      const isSourceLeft = sourceX < targetX
      const sourceHandle = `${rel.from_table}.${rel.from_column}.${isSourceLeft ? 'right' : 'left'}`
      const targetHandle = `${rel.to_table}.${rel.to_column}.${isSourceLeft ? 'left' : 'right'}`

      return {
        id: `${rel.from_table}.${rel.from_column}_to_${rel.to_table}.${rel.to_column}`,
        source: rel.from_table,
        target: rel.to_table,
        sourceHandle,
        targetHandle,
        type: 'relation',
        data: { fromColumn: rel.from_column, toColumn: rel.to_column },
      }
    })
  })

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
    customRelations: DiagramRelationData[]
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
      const customRelations: DiagramRelationData[] = []
      for (const node of latest.nodes ?? []) {
        posMap.set(node.table_name, { x: node.x_pos, y: node.y_pos })
        if (node.metadata?.columns) {
          customTables.push(node.metadata as TableData)
        }
      }
      for (const rel of latest.relations ?? []) {
        customRelations.push({
          from_table: rel.from_table,
          from_column: rel.from_column,
          to_table: rel.to_table,
          to_column: rel.to_column,
          type: rel.type ?? 'belongs_to',
          name: rel.name ?? null,
        })
      }
      return { positions: posMap, customTables, customRelations }
    } catch {
      return null
    }
  }

  function savePositions(connectionId: string, nodeList: any[], relList?: any[]) {
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

        const relationsData = (relList ?? []).map((r: any) => ({
          from_table: r.from_table,
          from_column: r.from_column,
          to_table: r.to_table,
          to_column: r.to_column,
          type: r.type ?? 'belongs_to',
          name: r.name ?? null,
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
              relations: relationsData,
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
              relations: relationsData,
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

      // Merge custom relations from saved diagram
      const customRelations = saved?.customRelations ?? []
      const existingRels = new Set(data.relations.map((r: SchemaRelation) =>
        `${r.from_table}.${r.from_column}->${r.to_table}.${r.to_column}`
      ))
      for (const cr of customRelations) {
        const key = `${cr.from_table}.${cr.from_column}->${cr.to_table}.${cr.to_column}`
        if (!existingRels.has(key)) {
          data.relations.push(cr as SchemaRelation)
          existingRels.add(key)
        }
      }

      // Also populate the relations ref so auto-save preserves them
      relations.value = customRelations

      buildGraph(data, saved?.positions ?? null)
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load schema'
    } finally {
      loading.value = false
    }
  }

  function buildGraph(data: SchemaResponse, savedPositions?: Map<string, { x: number; y: number }> | null) {
    // Build foreignKey map from both schema and custom relations
    const fkMap = new Map<string, ForeignKeyData[]>()
    const allSourceRels = [...data.relations, ...relations.value]
    for (const rel of allSourceRels) {
      if (!fkMap.has(rel.from_table)) fkMap.set(rel.from_table, [])
      const existing = fkMap.get(rel.from_table)!
      const dup = existing.some(fk => fk.column === rel.from_column && fk.referencesTable === rel.to_table)
      if (!dup) {
        existing.push({
          column: rel.from_column,
          referencesTable: rel.to_table,
          referencesColumn: rel.to_column,
        })
      }
    }

    const graphNodes: Node[] = data.tables.map((table) => ({
      id: table.name,
      type: 'table',
      position: { x: 0, y: 0 },
      data: {
        tableName: table.name,
        columns: table.columns,
        indexes: table.indexes,
        foreignKeys: fkMap.get(table.name) ?? [],
        rowCount: table.row_count,
        sizeKb: table.size_mb * 1000,
      } as TableData,
    }))

    // Build edge list for dagre layout only (handles are computed dynamically)
    const allRels = [...data.relations, ...relations.value]
    const seen = new Set<string>()
    const uniqueRels = allRels.filter(r => {
      const key = `${r.from_table}.${r.from_column}->${r.to_table}.${r.to_column}`
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })

    const graphEdges: Edge[] = uniqueRels.map((rel) => ({
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

  function addForeignKeysToRelations(tableName: string, foreignKeys: ForeignKeyData[]) {
    if (!foreignKeys || foreignKeys.length === 0) return

    const existing = new Set(relations.value.map(r =>
      `${r.from_table}.${r.from_column}->${r.to_table}.${r.to_column}`
    ))

    for (const fk of foreignKeys) {
      const key = `${tableName}.${fk.column}->${fk.referencesTable}.${fk.referencesColumn}`
      if (!existing.has(key)) {
        const newRel: DiagramRelationData = {
          from_table: tableName,
          from_column: fk.column,
          to_table: fk.referencesTable,
          to_column: fk.referencesColumn,
          type: 'belongs_to',
          name: `fk_${tableName}_${fk.column}`,
        }
        relations.value.push(newRel)
        existing.add(key)
      }
    }
  }

  // --- Dynamic edge reactivity during drag ---
  // triggerRef forces Vue computed (edges) to re-evaluate without replacing the array,
  // so Vue Flow's internal drag state is preserved

  function notifyDrag() {
    triggerRef(nodes)
  }

  function rebuildEdges() {
    // Obsolete: edges are now computed dynamically from schema + relations + node positions.
    // This function is kept for backward compatibility — callers don't need it anymore.
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
    relations,
    addForeignKeysToRelations,
    rebuildEdges,
    notifyDrag,
  }
}
