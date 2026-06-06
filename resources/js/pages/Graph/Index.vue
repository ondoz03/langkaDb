<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { Node } from '@vue-flow/core'
import { computed, onMounted, ref, watch } from 'vue'
import SchemaGraph from '@/components/graph/SchemaGraph.vue'
import TableDetailPanel from '@/components/graph/TableDetailPanel.vue'
import { useGraph } from '@/composables/useGraph'
import { useConnectionStore } from '@/stores/connection'
import { Search, RefreshCw } from 'lucide-vue-next'

const store = useConnectionStore()
const { nodes, edges, getFilteredNodes, loading, hoveredNode, selectedNode, searchQuery, showOnlyConnected, loadSchema, onNodeClick, closePanel, onViewportChange, rearrange } = useGraph()

const filteredNodes = computed(() => getFilteredNodes())

onMounted(async () => {
  if (store.activeConnection) await loadSchema(store.activeConnection.id)
})

watch(() => store.activeConnectionId, async (id) => {
  if (id && store.activeConnection) await loadSchema(store.activeConnection.id)
})

function handleNodeClick(node: Node) { onNodeClick(node) }
function handleNodeEnter(nodeId: string) { hoveredNode.value = nodeId }
function handleNodeLeave() { hoveredNode.value = null }
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
      <!-- Toolbar -->
      <div class="flex items-center gap-2 border-b border-border px-6 py-2.5">
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
        <div class="ml-auto text-xs text-muted-foreground">
          {{ nodes.length }} tables
          <span v-if="edges.length"> · {{ edges.length }} relations</span>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">Loading schema...</div>

      <!-- Graph -->
      <div v-else class="flex-1">
        <SchemaGraph
          :nodes="filteredNodes"
          :edges="edges"
          :loading="loading"
          :hovered-node="hoveredNode"
          @node-click="handleNodeClick"
          @node-enter="handleNodeEnter"
          @node-leave="handleNodeLeave"
          @viewport-change="onViewportChange"
        />
      </div>
    </template>
  </div>

  <TableDetailPanel
    :open="selectedNode !== null"
    :table="selectedNode"
    @close="closePanel"
  />
</template>
