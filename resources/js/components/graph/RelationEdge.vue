<script setup lang="ts">
import type { EdgeProps } from '@vue-flow/core'
import { BaseEdge, getBezierPath } from '@vue-flow/core'
import { computed } from 'vue'

const props = defineProps<EdgeProps>()

const path = computed(() => {
  const [p] = getBezierPath({
    sourceX: props.sourceX,
    sourceY: props.sourceY,
    targetX: props.targetX,
    targetY: props.targetY,
    sourcePosition: props.sourcePosition,
    targetPosition: props.targetPosition,
    curvature: 0.25,
  })

  return p
})

const edgeColor = computed(() => {
  if (props.selected) return '#a78bfa'
  return '#8b5cf6'  // violet-500 for FK relations
})

const strokeDash = computed(() => {
  return props.selected ? 'none' : '6,3'
})
</script>

<template>
  <BaseEdge
    :id="props.id"
    :path="path"
    :style="{
      stroke: edgeColor,
      strokeWidth: props.selected ? 2.5 : 1.8,
      strokeDasharray: strokeDash,
      opacity: props.selected ? 1 : 0.7,
      transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
      filter: props.selected
        ? 'drop-shadow(0 0 6px rgba(139, 92, 246, 0.5))'
        : 'drop-shadow(0 0 3px rgba(139, 92, 246, 0.2))',
    }"
    :class="[
      'relation-edge',
      { 'relation-edge--selected': props.selected }
    ]"
  />
</template>

<style scoped>
.relation-edge {
  cursor: pointer;
}

.relation-edge:hover {
  filter: drop-shadow(0 0 6px rgba(139, 92, 246, 0.6)) !important;
  opacity: 1 !important;
}
</style>
