<script setup lang="ts">
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import { VueFlow } from '@vue-flow/core'
import type { Node, Edge } from '@vue-flow/core'
import { MiniMap } from '@vue-flow/minimap'
import RelationEdge from '@/components/graph/RelationEdge.vue'
import TableNode from '@/components/graph/TableNode.vue'

import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import '@vue-flow/controls/dist/style.css'
import '@vue-flow/minimap/dist/style.css'

interface Props {
  nodes: Node[]
  edges: Edge[]
  loading?: boolean
  hoveredNode?: string | null
}

defineProps<Props>()

const emit = defineEmits<{
  (e: 'node-click', node: Node): void
  (e: 'node-enter', nodeId: string): void
  (e: 'node-leave'): void
  (e: 'viewport-change', viewport: { x: number; y: number; zoom: number }): void
}>()
</script>

<template>
  <div class="relative h-full w-full overflow-hidden">
    <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-background/50 font-mono text-sm text-muted-foreground">
      Loading schema graph...
    </div>

    <VueFlow
      :key="`vf-${nodes.length}`"
      :nodes="nodes"
      :edges="edges"
      :default-viewport="{ x: 0, y: 0, zoom: 0.6 }"
      fit-view-on-init
      :min-zoom="0.05"
      :max-zoom="4"
      :node-styles="(n: Node) => ({ opacity: hoveredNode && hoveredNode !== n.id ? 0.3 : 1, transition: 'all 0.15s ease' })"
      :default-edge-options="{ style: { stroke: '#525252', strokeWidth: 1 } }"
      class="h-full w-full"
      @node-click="emit('node-click', $event.node)"
      @node-enter="emit('node-enter', $event.node.id)"
      @node-leave="emit('node-leave')"
      @viewport-change="emit('viewport-change', { x: $event.x, y: $event.y, zoom: $event.zoom })"
    >
      <Background :gap="24" pattern-color="#262626" />

      <Controls
        show-zoom
        show-fit-view
        position="bottom-right"
        class="!font-mono"
      />

      <MiniMap
        node-color="#262626"
        mask-color="rgba(0,0,0,0.6)"
        class="!bottom-16 !left-3 !top-auto !right-auto !border !border-border !shadow-lg"
        :style="{ background: 'hsl(0 0% 7%)' }"
      />

      <template #node-table="nodeProps">
        <TableNode v-bind="nodeProps" />
      </template>
      <template #edge-relation="edgeProps">
        <RelationEdge v-bind="edgeProps" />
      </template>
    </VueFlow>
  </div>
</template>

<style>
.vue-flow {
  background: hsl(var(--background));
}

.vue-flow__node {
  cursor: pointer;
}

.vue-flow__controls {
  display: flex;
  box-shadow: none;
  border: 1px solid hsl(var(--border));
  overflow: hidden;
}

.vue-flow__controls button {
  width: 28px;
  height: 28px;
  border: none;
  border-right: 1px solid hsl(var(--border));
  background: hsl(var(--card));
  fill: hsl(var(--foreground));
  cursor: pointer;
}

.vue-flow__controls button:last-child {
  border-right: none;
}

.vue-flow__controls button:hover {
  background: hsl(var(--accent));
}

.vue-flow__minimap {
  border-radius: 0 !important;
}
</style>
