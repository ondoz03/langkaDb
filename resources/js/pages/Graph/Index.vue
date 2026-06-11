<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { Node } from '@vue-flow/core'
import { computed, onMounted, ref, watch } from 'vue'
import SchemaGraph from '@/components/graph/SchemaGraph.vue'
import TableDetailPanel from '@/components/graph/TableDetailPanel.vue'
import ImportSqlDialog from '@/components/graph/ImportSqlDialog.vue'
import SchemaDiffViewer from '@/components/graph/SchemaDiffViewer.vue'
import SchemaToolbox from '@/components/graph/SchemaToolbox.vue'
import PromptToErdDialog from '@/components/graph/PromptToErdDialog.vue'
import { useGraph } from '@/composables/useGraph'
import { useExport } from '@/composables/useExport'
import { useSchemaSnapshot } from '@/composables/useSchemaSnapshot'
import { useConnectionStore } from '@/stores/connection'
import type { TableData } from '@/composables/useGraph'
import { Search, RefreshCw, Download, ChevronDown, Image, FileType, Upload, Camera, ArrowLeftRight, X, AlertTriangle, PanelLeft, Sparkles } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

const store = useConnectionStore()
const { nodes, edges, getFilteredNodes, loading, saving, hoveredNode, selectedNode, searchQuery, showOnlyConnected, loadSchema, buildGraph, savePositions, onNodeClick, closePanel, onViewportChange, rearrange, schema } = useGraph()
const { loading: exporting, exportSql, exportDocx } = useExport()
const { snapshots, loading: snapshotLoading, fetchSnapshots, createSnapshot, deleteSnapshot, computeDiff } = useSchemaSnapshot()

const filteredNodes = computed(() => getFilteredNodes())
const showToolbox = ref(true)
const canvasTableNames = computed(() => (nodes.value as any[]).map((n: any) => n.id) as string[])
const exportMenuOpen = ref(false)
const importDialogOpen = ref(false)
const promptDialogOpen = ref(false)
const snapshotMenuOpen = ref(false)
const showDiff = ref(false)
const diffData = ref<any>(null)
const diffLabelA = ref('')
const diffLabelB = ref('')
const newSnapshotLabel = ref('')

function collectTableData(): TableData[] {
  const canvasNodes = nodes.value as Node[]
  if (!schema.value) return canvasNodes.map(n => n.data as TableData)

  const onCanvas = new Set(canvasNodes.map(n => n.id))
  const all: TableData[] = schema.value.tables.map(t => ({
    tableName: t.name,
    columns: t.columns as TableData['columns'],
    indexes: (t.indexes ?? []) as TableData['indexes'],
    rowCount: t.row_count,
    sizeKb: t.size_mb * 1000,
  }))
  const canvasTables: TableData[] = canvasNodes.map(n => n.data as TableData)

  const seen = new Set<string>()
  return [...all, ...canvasTables].filter(t => {
    if (seen.has(t.tableName)) return false
    seen.add(t.tableName)
    return true
  })
}

const allTableData = computed(collectTableData)
const selectedSnapshotA = ref<number | null>(null)
const selectedSnapshotB = ref<number | null>(null)

onMounted(async () => {
  if (store.activeConnection) {
    await loadSchema(store.activeConnection.id)
    await fetchSnapshots(store.activeConnection.id)
  }
})

watch(() => store.activeConnectionId, async (id) => {
  if (id && store.activeConnection) {
    await loadSchema(store.activeConnection.id)
    await fetchSnapshots(store.activeConnection.id)
  } else {
    showDiff.value = false
  }
})

// Close dropdowns on outside click
if (typeof document !== 'undefined') {
  document.addEventListener('click', (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (snapshotMenuOpen.value) {
      if (!target.closest('.snapshot-menu-btn') && !target.closest('.snapshot-menu-dropdown')) {
        snapshotMenuOpen.value = false
      }
    }
    if (exportMenuOpen.value) {
      if (!target.closest('.export-menu-btn') && !target.closest('.export-menu-dropdown')) {
        exportMenuOpen.value = false
      }
    }
  })
}

function handleNodeClick(node: Node) { onNodeClick(node) }
function handleNodeEnter(nodeId: string) { hoveredNode.value = nodeId }
function handleNodeLeave() { hoveredNode.value = null }

async function exportPng() {
  const el = document.querySelector('.vue-flow')
  if (!el) { toast.error('Graph pane not found'); return }
  exportMenuOpen.value = false
  try {
    const { toPng } = await import('html-to-image')
    const dataUrl = await toPng(el as HTMLElement, {
      backgroundColor: '#0a0a0a',
      pixelRatio: 2,
      cacheBust: true,
      filter: (node) => {
        if (node instanceof HTMLElement) {
          return !node.classList?.contains('vue-flow__minimap') && !node.classList?.contains('vue-flow__controls')
        }
        return true
      },
    })
    const link = document.createElement('a')
    link.download = `schema-${store.activeConnection?.name ?? 'graph'}.png`
    link.href = dataUrl
    link.click()
    toast.success('Graph exported as PNG')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : 'Failed to export PNG')
  }
}

async function exportSvg() {
  const el = document.querySelector('.vue-flow')
  if (!el) { toast.error('Graph pane not found'); return }
  exportMenuOpen.value = false
  try {
    const { toSvg } = await import('html-to-image')
    const dataUrl = await toSvg(el as HTMLElement, {
      backgroundColor: '#0a0a0a',
      cacheBust: true,
      filter: (node) => {
        if (node instanceof HTMLElement) {
          return !node.classList?.contains('vue-flow__minimap') && !node.classList?.contains('vue-flow__controls')
        }
        return true
      },
    })
    const link = document.createElement('a')
    link.download = `schema-${store.activeConnection?.name ?? 'graph'}.svg`
    link.href = dataUrl
    link.click()
    toast.success('Graph exported as SVG')
  } catch (e) {
    toast.error(e instanceof Error ? e.message : 'Failed to export SVG')
  }
}

function handleImported(data: any) {
  buildGraph(data)
}

async function handleCreateSnapshot() {
  if (!store.activeConnection || !newSnapshotLabel.value.trim()) return
  const snap = await createSnapshot(store.activeConnection.id, newSnapshotLabel.value.trim())
  newSnapshotLabel.value = ''
  snapshotMenuOpen.value = false
  if (snap) await fetchSnapshots(store.activeConnection.id)
}

function handleAddTable(tableData: TableData) {
  const exists = nodes.value.some(n => n.id === tableData.tableName)
  if (exists) return

  const newNode: Node = {
    id: tableData.tableName,
    type: 'table',
    position: { x: 100 + nodes.value.length * 30, y: 100 + nodes.value.length * 30 },
    data: tableData,
  }

  const copy = [...nodes.value as any[]]
  copy.push(newNode)
  nodes.value = copy as any
}

function handleCreateTable(tableData: TableData) {
  const idx = (nodes.value as any[]).findIndex((n: any) => n.id === tableData.tableName)
  if (idx >= 0) {
    nodes.value = (nodes.value as any[]).map((n: any) =>
      n.id === tableData.tableName
        ? { ...n, data: tableData }
        : n
    ) as any
  } else {
    handleAddTable(tableData)
  }
}

function handleRemoveTable(tableName: string) {
  nodes.value = (nodes.value as any[]).filter((n: any) => n.id !== tableName) as any
  if (selectedNode.value?.tableName === tableName) {
    closePanel()
  }
}

function handleEditTable(tableData: TableData) {
  // Already handled by CreateTableDialog via editTable prop
}

function handleDropOnGraph(e: DragEvent) {
  const raw = e.dataTransfer?.getData('application/json')
  if (!raw) return
  try {
    const table = JSON.parse(raw) as TableData
    handleAddTable(table)
  } catch {
    // ignore invalid drops
  }
}

function handlePromptImported(data: any) {
  buildGraph(data)
  promptDialogOpen.value = false
}

function handleNodeDragStop(node: any) {
  if (store.activeConnectionId) {
    savePositions(store.activeConnectionId, nodes.value as any[])
  }
}

watch(() => (nodes.value as any[]).length, () => {
  if (store.activeConnectionId && (nodes.value as any[]).length > 0) {
    savePositions(store.activeConnectionId, nodes.value as any[])
  }
})

async function handleComputeDiff() {
  if (!selectedSnapshotA.value || !selectedSnapshotB.value) return
  const a = snapshots.value.find((s: any) => s.id === selectedSnapshotA.value)
  const b = snapshots.value.find((s: any) => s.id === selectedSnapshotB.value)
  if (!a || !b) return
  diffLabelA.value = a.label
  diffLabelB.value = b.label
  const result = await computeDiff(selectedSnapshotB.value, selectedSnapshotA.value)
  if (result) {
    diffData.value = result
    showDiff.value = true
  }
}
</script>

<template>
  <Head title="Database Graph" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">Database Graph</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Visualize your database structure</p>
      </div>
      <div v-if="store.activeConnection" class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground">
        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
        {{ store.activeConnection.name }}
      </div>
    </div>

    <!-- No active connection -->
    <div v-if="!store.activeConnection" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Search class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No active connection</p>
          <p class="mt-1 text-sm text-muted-foreground">Connect to a database first to view the schema graph.</p>
        </div>
      </div>
    </div>

    <template v-else>
      <div class="flex flex-1 overflow-hidden">
        <!-- Schema Toolbox -->
        <div
          v-show="showToolbox"
          class="w-56 shrink-0 overflow-hidden transition-all"
        >
          <SchemaToolbox
            :tables="allTableData"
            :canvas-table-names="canvasTableNames"
            @add-table="handleAddTable"
            @remove-table="handleRemoveTable"
            @edit-table="handleEditTable"
            @create-table="handleCreateTable"
          />
        </div>

        <!-- Main graph area -->
        <div class="flex flex-1 flex-col overflow-hidden">
          <!-- Toolbar -->
          <div class="flex items-center gap-2 border-b border-border px-6 py-2.5">
            <button
              class="rounded-md border border-border bg-card p-1.5 text-muted-foreground transition-colors hover:text-foreground"
              :class="{ 'bg-accent text-foreground': showToolbox }"
              @click="showToolbox = !showToolbox"
              title="Toggle Schema Toolbox"
            >
              <PanelLeft class="h-3.5 w-3.5" />
            </button>
        <div class="relative flex-1 max-w-xs">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search tables..."
            class="w-full rounded-md border border-border bg-card py-1.5 pl-8 pr-3 text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
          />
        </div>
        <button
          class="rounded-md border border-border bg-card px-2.5 py-1.5 text-xs transition-colors"
          :class="showOnlyConnected ? 'bg-accent text-foreground' : 'text-muted-foreground hover:text-foreground'"
          @click="showOnlyConnected = !showOnlyConnected"
        >
          Connected only
        </button>
        <button
          class="rounded-md border border-border bg-card px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
          @click="rearrange"
        >
          <RefreshCw class="h-3.5 w-3.5" />
        </button>

        <button
          class="flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
          @click="importDialogOpen = true"
        >
          <Upload class="h-3.5 w-3.5" />
          Import SQL
        </button>
        <button
          class="flex items-center gap-1 rounded-md border border-accent-brand/30 bg-accent-brand/10 px-2.5 py-1.5 text-xs text-accent-brand transition-colors hover:bg-accent-brand/20"
          @click="promptDialogOpen = true"
        >
          <Sparkles class="h-3.5 w-3.5" />
          AI Schema
        </button>

        <!-- Snapshot menu -->
        <div class="relative snapshot-menu-btn">
          <button
            class="flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
            @click.stop="snapshotMenuOpen = !snapshotMenuOpen"
          >
            <Camera class="h-3.5 w-3.5" />
            Snapshots
            <ChevronDown class="h-3 w-3" />
          </button>
          <div
            v-if="snapshotMenuOpen"
            class="snapshot-menu-dropdown absolute right-0 top-full z-50 mt-1 w-72 rounded-md border border-border bg-card py-2 shadow-lg"
          >
            <div class="px-3 pb-2">
              <p class="text-xs font-medium text-foreground">Save Snapshot</p>
              <div class="mt-1.5 flex gap-1.5">
                <input
                  v-model="newSnapshotLabel"
                  type="text"
                  placeholder="e.g. Before optimization"
                  class="min-w-0 flex-1 rounded-md border border-border bg-black/20 px-2 py-1.5 text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
                  @keyup.enter="handleCreateSnapshot"
                />
                <button
                  class="rounded-md bg-foreground px-2 py-1.5 text-xs font-medium text-background disabled:opacity-50"
                  :disabled="!newSnapshotLabel.trim() || snapshotLoading"
                  @click="handleCreateSnapshot"
                >
                  Save
                </button>
              </div>
            </div>
            <div v-if="snapshots.length > 0" class="border-t border-border px-3 pt-2">
              <p class="mb-1.5 text-xs font-medium text-foreground">Saved Snapshots</p>
              <div class="mb-2 max-h-40 space-y-1 overflow-y-auto">
                <div v-for="s in snapshots" :key="s.id" class="flex items-center justify-between rounded-md px-2 py-1 text-xs hover:bg-accent/50">
                  <div class="flex flex-col">
                    <span class="text-foreground">{{ s.label }}</span>
                    <span class="text-muted-foreground">{{ new Date(s.created_at).toLocaleString() }}</span>
                  </div>
                  <button
                    class="rounded p-0.5 text-muted-foreground hover:text-red-500"
                    @click="deleteSnapshot(s.id)"
                    title="Delete snapshot"
                    type="button"
                  >
                    <X class="h-3 w-3" />
                  </button>
                </div>
              </div>
            </div>
            <div v-if="snapshots.length >= 2" class="border-t border-border px-3 pt-2">
              <p class="mb-1.5 text-xs font-medium text-foreground">Compare Snapshots</p>
              <div class="flex flex-col gap-1.5">
                <select
                  v-model="selectedSnapshotA"
                  class="rounded-md border border-border bg-black/20 px-2 py-1.5 text-xs text-foreground outline-none focus:border-ring"
                >
                  <option :value="null" disabled>Older</option>
                  <option v-for="s in [...snapshots].reverse()" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
                <select
                  v-model="selectedSnapshotB"
                  class="rounded-md border border-border bg-black/20 px-2 py-1.5 text-xs text-foreground outline-none focus:border-ring"
                >
                  <option :value="null" disabled>Newer</option>
                  <option v-for="s in [...snapshots].reverse()" :key="s.id" :value="s.id">{{ s.label }}</option>
                </select>
                <button
                  class="flex items-center justify-center gap-1 rounded-md bg-foreground px-2 py-1.5 text-xs font-medium text-background disabled:opacity-50"
                  :disabled="!selectedSnapshotA || !selectedSnapshotB || snapshotLoading"
                  @click="handleComputeDiff"
                >
                  <ArrowLeftRight class="h-3 w-3" />
                  View Diff
                </button>
              </div>
            </div>
            <div v-if="snapshots.length === 0" class="px-3 py-2 text-xs text-muted-foreground">
              No snapshots yet. Save your first snapshot above.
            </div>
          </div>
        </div>

        <!-- Export dropdown -->
        <div class="relative export-menu-btn">
          <button
            class="flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
            :disabled="exporting"
            @click="exportMenuOpen = !exportMenuOpen"
          >
            <Download class="h-3.5 w-3.5" />
            Export
            <ChevronDown class="h-3 w-3" />
          </button>
          <div
            v-if="exportMenuOpen"
            class="export-menu-dropdown absolute right-0 top-full z-50 mt-1 w-48 rounded-md border border-border bg-card py-1 shadow-lg"
          >
            <button
              class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-foreground hover:bg-accent"
              @click="exportSql(store.activeConnection!.id); exportMenuOpen = false"
              type="button"
            >
              <Download class="h-3.5 w-3.5" />
              Export SQL DDL
            </button>
            <button
              class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-foreground hover:bg-accent disabled:opacity-50"
              :disabled="exporting"
              @click="exportDocx(store.activeConnection!.id); exportMenuOpen = false"
              type="button"
            >
              <FileType class="h-3.5 w-3.5" />
              Export Data Dictionary (DOCX)
            </button>
            <button
              class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-foreground hover:bg-accent"
              @click="exportPng"
              type="button"
            >
              <Image class="h-3.5 w-3.5" />
              Export as PNG
            </button>
            <button
              class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-foreground hover:bg-accent"
              @click="exportSvg"
              type="button"
            >
              <FileType class="h-3.5 w-3.5" />
              Export as SVG
            </button>
          </div>
        </div>

        <div class="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
          <span v-if="saving" class="text-[10px]">saving...</span>
          {{ nodes.length }} tables
          <span v-if="edges.length"> · {{ edges.length }} relations</span>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">Loading schema...</div>

      <!-- Diff View -->
      <div v-else-if="showDiff && diffData" class="flex-1 overflow-hidden">
        <SchemaDiffViewer
          :diff="diffData"
          :snapshot-label-a="diffLabelA"
          :snapshot-label-b="diffLabelB"
          @close="showDiff = false"
        />
      </div>

          <!-- Graph drop zone -->
          <div
            v-else class="flex-1"
            @dragover.prevent
            @drop.prevent="handleDropOnGraph"
          >
            <SchemaGraph
              :nodes="filteredNodes"
              :edges="edges"
              :loading="loading"
              :hovered-node="hoveredNode"
            @node-click="handleNodeClick"
            @node-enter="handleNodeEnter"
            @node-leave="handleNodeLeave"
            @node-drag-stop="handleNodeDragStop"
            @viewport-change="onViewportChange"
            />
          </div>
        </div>
      </div>
    </template>
  </div>

  <TableDetailPanel
    :open="selectedNode !== null"
    :table="selectedNode"
    @close="closePanel"
  />

  <ImportSqlDialog
    :open="importDialogOpen"
    @close="importDialogOpen = false"
    @imported="handleImported"
  />

  <PromptToErdDialog
    :open="promptDialogOpen"
    @close="promptDialogOpen = false"
    @imported="handlePromptImported"
  />
</template>
