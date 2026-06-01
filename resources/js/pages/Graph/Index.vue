<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { Node } from '@vue-flow/core'
import { computed, onMounted, watch } from 'vue'
import SchemaGraph from '@/components/graph/SchemaGraph.vue'
import TableDetailPanel from '@/components/graph/TableDetailPanel.vue'
import { useGraph } from '@/composables/useGraph'
import { useConnectionStore } from '@/stores/connection'

const store = useConnectionStore()
const { nodes, edges, getFilteredNodes, loading, hoveredNode, selectedNode, searchQuery, showOnlyConnected, loadSchema, onNodeClick, closePanel, onViewportChange, rearrange } = useGraph()

const filteredNodes = computed(() => getFilteredNodes())

onMounted(async () => {
  if (store.activeConnection) {
    await loadSchema(store.activeConnection.id)
  }
})

watch(() => store.activeConnectionId, async (id) => {
  if (id && store.activeConnection) {
    await loadSchema(store.activeConnection.id)
  }
})

function handleNodeClick(node: Node) {
  onNodeClick(node)
}

function handleNodeEnter(nodeId: string) {
  hoveredNode.value = nodeId
}

function handleNodeLeave() {
  hoveredNode.value = null
}
</script>

<template>
  <Head title="Database Graph" />

  <div class="flex h-full flex-1 flex-col font-mono">
    <div class="flex items-center justify-between border-b border-border px-4 py-2">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-medium text-foreground">Database Graph</h2>
        <span class="text-xs text-muted-foreground">· {{ store.activeConnection?.name }}</span>
      </div>
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <span>{{ nodes.length }} tables</span>
        <span v-if="edges.length"> · {{ edges.length }} relations</span>
      </div>
    </div>

    <div class="flex items-center gap-2 border-b border-border px-4 py-1.5">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search tables..."
        class="flex-1 rounded-lg border border-border bg-card px-2 py-1 text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-accent-brand/50 focus:ring-1 focus:ring-accent-brand/20 transition-colors"
      />

      <button
        class="rounded-lg border border-border bg-card px-2 py-1 text-xs text-foreground hover:bg-accent transition-colors"
        :class="{ 'bg-accent': showOnlyConnected }"
        title="Show only connected tables"
        @click="showOnlyConnected = !showOnlyConnected"
      >
        Connected
      </button>

      <button
        class="rounded-lg border border-border bg-card px-2 py-1 text-xs text-foreground hover:bg-accent transition-colors"
        title="Rearrange layout"
        @click="rearrange"
      >
        Rearrange
      </button>
    </div>

    <div v-if="!store.activeConnection" class="flex flex-1 items-center justify-center font-mono text-sm text-muted-foreground">
      No active connection. Connect to a database first.
    </div>

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
  </div>

  <TableDetailPanel
    :open="selectedNode !== null"
    :table="selectedNode"
    @close="closePanel"
  />
</template>
