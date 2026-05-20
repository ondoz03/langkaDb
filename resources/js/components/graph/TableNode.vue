<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core'
import type { NodeProps } from '@vue-flow/core'

interface Column {
  name: string
  type: string
  nullable: boolean
  primary: boolean
}

interface TableData {
  tableName: string
  columns: Column[]
  rowCount: number
  sizeKb: number
}

const props = defineProps<NodeProps>()
const data = props.data as TableData
</script>

<template>
  <div
    class="min-w-[200px] border border-border bg-card font-mono text-foreground shadow-lg"
    :style="{ width: 'auto' }"
  >
    <div class="flex items-center justify-between border-b border-border bg-primary/5 px-3 py-2">
      <span class="text-sm font-bold tracking-tight whitespace-nowrap">{{ data.tableName }}</span>
      <span class="ml-3 text-xs text-muted-foreground whitespace-nowrap">{{ data.columns?.length ?? 0 }} cols</span>
    </div>

    <div class="divide-y divide-border">
      <div
        v-for="col in (data.columns ?? [])"
        :key="col.name"
        class="flex items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap hover:bg-accent/30"
      >
        <Handle
          :id="`${data.tableName}.${col.name}`"
          type="source"
          :position="Position.Left"
          class="!relative !inset-auto !transform-none !h-1.5 !w-1.5 !rounded-full !border-0"
          :class="col.primary ? '!bg-amber-500' : '!bg-muted-foreground/30'"
        />

        <span
          :class="{ 'font-semibold text-amber-500': col.primary }"
        >{{ col.name }}</span>
        <span class="text-muted-foreground ml-auto">{{ col.type }}</span>

        <Handle
          :id="`${data.tableName}.${col.name}`"
          type="target"
          :position="Position.Right"
          class="!relative !inset-auto !transform-none !h-1.5 !w-1.5 !rounded-full !border-0"
          :class="col.primary ? '!bg-amber-500' : '!bg-muted-foreground/30'"
        />
      </div>
    </div>

    <div
      v-if="(data.rowCount ?? 0) > 0 || (data.sizeKb ?? 0) > 0"
      class="border-t border-border px-3 py-1.5 text-xs text-muted-foreground/60"
    >
      <span v-if="data.rowCount > 0">{{ data.rowCount.toLocaleString() }} rows</span>
      <span v-if="data.sizeKb > 0"> · {{ (data.sizeKb / 1000).toFixed(1) }} MB</span>
    </div>
  </div>
</template>
