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
  })

  return p
})
</script>

<template>
  <BaseEdge
    :id="props.id"
    :path="path"
    :style="{
      stroke: props.selected ? '#818cf8' : '#525252',
      strokeWidth: props.selected ? 2 : 1,
      opacity: 1,
      transition: 'all 0.15s ease',
    }"
  />
</template>
