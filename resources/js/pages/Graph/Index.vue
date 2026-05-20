<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import type { Node } from '@vue-flow/core'
import { onMounted, ref } from 'vue'
import SchemaGraph from '@/components/graph/SchemaGraph.vue'
import TableDetailPanel from '@/components/graph/TableDetailPanel.vue'
import { useGraph } from '@/composables/useGraph'
import { useConnectionStore } from '@/stores/connection'
import type { Connection } from '@/stores/connection'

const store = useConnectionStore()
const { nodes, edges, loading, hoveredNode, selectedNode, loadSchema, onNodeClick, closePanel, onViewportChange } = useGraph()
const connError = ref<string | null>(null)

onMounted(async () => {
  if (store.connections.length === 0) {
    try {
      const res = await fetch('/api/connections')
      const json = await res.json()

      if (json.data) {
        store.setConnections(json.data)
      }
    } catch {
      connError.value = 'Failed to load connections'

      return
    }
  }

  const conn = store.activeConnection ?? store.connections[0]

  if (conn) {
    selectConnection(conn)
  }
})

async function selectConnection(conn: Connection) {
  connError.value = null
  store.setActive(conn.id)
  await loadSchema(conn.id)
}

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
      <div class="flex items-center gap-3">
        <h2 class="text-sm font-medium text-foreground">Database Graph</h2>
        <select
          class="border border-border bg-card px-2 py-1 text-xs text-foreground outline-none"
          :value="store.activeConnectionId ?? ''"
          @change="(e) => { const conn = store.connections.find(c => c.id === (e.target as HTMLSelectElement).value); if (conn) selectConnection(conn) }"
        >
          <option value="" disabled>Select connection</option>
          <option
            v-for="conn in store.connections"
            :key="conn.id"
            :value="conn.id"
          >{{ conn.name }}</option>
        </select>
      </div>
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <span>{{ nodes.length }} tables</span>
        <span v-if="edges.length"> · {{ edges.length }} relations</span>
      </div>
    </div>

    <div v-if="connError" class="flex flex-1 items-center justify-center font-mono text-sm text-muted-foreground">
      {{ connError }}
    </div>

    <div v-else-if="store.connections.length === 0" class="flex flex-1 items-center justify-center font-mono text-sm text-muted-foreground">
      No database connections. Add one first.
    </div>

    <div v-else class="flex-1">
      <SchemaGraph
        :nodes="nodes"
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
