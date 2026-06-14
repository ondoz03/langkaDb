<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core'
import type { NodeProps } from '@vue-flow/core'
import { Link2 } from 'lucide-vue-next'
import { computed } from 'vue'

interface ForeignKey {
  column: string
  referencesTable: string
  referencesColumn: string
}

interface Column {
  name: string
  type: string
  nullable: boolean
  primary: boolean
}

interface TableData {
  tableName: string
  columns: Column[]
  foreignKeys?: ForeignKey[]
  rowCount: number
  sizeKb: number
}

const props = defineProps<NodeProps>()
const data = props.data as TableData

const fkColumnSet = computed(() => {
  const set = new Set<string>()
  for (const fk of data.foreignKeys ?? []) {
    set.add(fk.column)
  }
  return set
})

function fkInfo(colName: string): string {
  const fks = (data.foreignKeys ?? []).filter(fk => fk.column === colName)
  if (fks.length === 0) return ''
  return fks.map(fk => `→ ${fk.referencesTable}.${fk.referencesColumn}`).join(', ')
}
</script>

<template>
  <div
    class="min-w-[200px] border border-border bg-card font-mono text-foreground shadow-lg"
    :style="{ width: 'auto' }"
  >
    <div class="flex items-center justify-between border-b border-border bg-primary/5 px-3 py-2">
      <span class="text-sm font-bold tracking-tight whitespace-nowrap">{{ data.tableName }}</span>
      <div class="flex items-center gap-2">
        <span v-if="fkColumnSet.size > 0" class="flex items-center gap-1 text-[10px] text-accent-brand/70">
          <Link2 class="h-3 w-3" />
          {{ fkColumnSet.size }}
        </span>
        <span class="text-xs text-muted-foreground whitespace-nowrap">{{ data.columns?.length ?? 0 }} cols</span>
      </div>
    </div>

    <div class="divide-y divide-border">
      <div
        v-for="col in (data.columns ?? [])"
        :key="col.name"
        class="group flex items-center gap-2 px-3 py-1.5 text-xs whitespace-nowrap hover:bg-accent/30"
        :title="fkInfo(col.name) || col.name"
      >
        <!-- Left side: both source+target handles + indicator dot -->
        <div class="relative h-1.5 w-1.5 shrink-0 flex items-center justify-center">
          <Handle
            :id="`${data.tableName}.${col.name}.left`"
            type="source"
            :position="Position.Left"
            class="!absolute !inset-0 !h-full !w-full !rounded-full !border-0 !bg-transparent"
          />
          <Handle
            :id="`${data.tableName}.${col.name}.left`"
            type="target"
            :position="Position.Left"
            class="!absolute !inset-0 !h-full !w-full !rounded-full !border-0 !bg-transparent"
          />
          <div
            class="relative z-10 h-2 w-2 rounded-full ring-1 ring-background"
            :class="{
              'bg-violet-500 shadow-[0_0_4px_rgba(139,92,246,0.6)]': fkColumnSet.has(col.name),
              'bg-amber-500 shadow-[0_0_4px_rgba(245,158,11,0.6)]': col.primary && !fkColumnSet.has(col.name),
              'bg-muted-foreground/30': !col.primary && !fkColumnSet.has(col.name),
            }"
          />
        </div>

        <span
          class="flex items-center gap-1"
          :class="{
            'font-semibold text-amber-400': col.primary && !fkColumnSet.has(col.name),
            'text-violet-400 font-medium': fkColumnSet.has(col.name) && !col.primary,
            'font-semibold text-amber-400': col.primary && fkColumnSet.has(col.name),
          }"
        >
          <span v-if="col.primary" class="text-[9px] mr-0.5">🔑</span>
          {{ col.name }}
          <Link2
            v-if="fkColumnSet.has(col.name)"
            class="h-2.5 w-2.5 text-violet-400 shrink-0"
          />
        </span>
        <span class="text-muted-foreground ml-auto">{{ col.type }}</span>

        <!-- Right side: both source+target handles + indicator dot -->
        <div class="relative h-1.5 w-1.5 shrink-0 flex items-center justify-center">
          <Handle
            :id="`${data.tableName}.${col.name}.right`"
            type="source"
            :position="Position.Right"
            class="!absolute !inset-0 !h-full !w-full !rounded-full !border-0 !bg-transparent"
          />
          <Handle
            :id="`${data.tableName}.${col.name}.right`"
            type="target"
            :position="Position.Right"
            class="!absolute !inset-0 !h-full !w-full !rounded-full !border-0 !bg-transparent"
          />
          <div
            class="relative z-10 h-2 w-2 rounded-full ring-1 ring-background"
            :class="{
              'bg-violet-500 shadow-[0_0_4px_rgba(139,92,246,0.6)]': fkColumnSet.has(col.name),
              'bg-amber-500 shadow-[0_0_4px_rgba(245,158,11,0.6)]': col.primary && !fkColumnSet.has(col.name),
              'bg-muted-foreground/30': !col.primary && !fkColumnSet.has(col.name),
            }"
          />
        </div>
      </div>
    </div>

    <div
      v-if="(data.foreignKeys ?? []).length > 0"
      class="border-t border-border px-3 py-1.5"
    >
      <div
        v-for="fk in (data.foreignKeys ?? [])"
        :key="`${fk.column}-${fk.referencesTable}`"
        class="flex items-center gap-1 text-[10px] text-accent-brand/70"
      >
        <Link2 class="h-2.5 w-2.5 shrink-0" />
        <span class="truncate">{{ fk.column }} → {{ fk.referencesTable }}.{{ fk.referencesColumn }}</span>
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
