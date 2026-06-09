<script setup lang="ts">
import type { DiffResult } from '@/composables/useSchemaSnapshot'
import { ArrowLeftRight, Plus, Minus, Pencil } from 'lucide-vue-next'

const props = defineProps<{
  diff: DiffResult
  snapshotLabelA: string
  snapshotLabelB: string
}>()

const emit = defineEmits<{
  close: []
}>()

function changeColor(type: string): string {
  switch (type) {
    case 'added': return 'text-green-500'
    case 'deleted': return 'text-red-500'
    case 'modified': return 'text-yellow-500'
    default: return 'text-muted-foreground'
  }
}

function changeIcon(type: string): any {
  switch (type) {
    case 'added': return Plus
    case 'deleted': return Minus
    case 'modified': return Pencil
    default: return ArrowLeftRight
  }
}
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div class="flex items-center gap-2">
        <ArrowLeftRight class="h-4 w-4 text-muted-foreground" />
        <div>
          <h2 class="text-sm font-semibold text-foreground">Schema Diff</h2>
          <p class="mt-0.5 text-xs text-muted-foreground">
            Comparing <span class="font-medium text-foreground">{{ snapshotLabelA }}</span>
            <span class="mx-1">→</span>
            <span class="font-medium text-foreground">{{ snapshotLabelB }}</span>
          </p>
        </div>
      </div>
      <button class="rounded-md border border-border bg-card px-2.5 py-1.5 text-xs text-muted-foreground hover:text-foreground" @click="emit('close')">
        Close Diff
      </button>
    </div>

    <!-- Summary bar -->
    <div class="flex gap-4 border-b border-border px-6 py-3 text-xs">
      <span class="flex items-center gap-1.5">
        <Plus class="h-3.5 w-3.5 text-green-500" />
        <span class="text-foreground">{{ diff.summary.additions }}</span> added
      </span>
      <span class="flex items-center gap-1.5">
        <Minus class="h-3.5 w-3.5 text-red-500" />
        <span class="text-foreground">{{ diff.summary.deletions }}</span> deleted
      </span>
      <span class="flex items-center gap-1.5">
        <Pencil class="h-3.5 w-3.5 text-yellow-500" />
        <span class="text-foreground">{{ diff.summary.modifications }}</span> modified
      </span>
    </div>

    <!-- Diff content -->
    <div class="flex-1 overflow-y-auto p-6">
      <!-- New tables -->
      <div v-if="diff.new_tables.length" class="mb-6">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-green-500">New Tables</h3>
        <div class="space-y-1.5">
          <div v-for="t in diff.new_tables" :key="t.name" class="flex items-center gap-2 rounded-md border border-green-500/20 bg-green-500/5 px-3 py-2">
            <Plus class="h-3.5 w-3.5 text-green-500" />
            <span class="text-sm text-foreground">{{ t.name }}</span>
            <span class="ml-auto text-xs text-muted-foreground">{{ t.columns.length }} columns</span>
          </div>
        </div>
      </div>

      <!-- Deleted tables -->
      <div v-if="diff.deleted_tables.length" class="mb-6">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-red-500">Deleted Tables</h3>
        <div class="space-y-1.5">
          <div v-for="t in diff.deleted_tables" :key="t.name" class="flex items-center gap-2 rounded-md border border-red-500/20 bg-red-500/5 px-3 py-2">
            <Minus class="h-3.5 w-3.5 text-red-500" />
            <span class="text-sm text-foreground">{{ t.name }}</span>
            <span class="ml-auto text-xs text-muted-foreground">{{ t.columns.length }} columns</span>
          </div>
        </div>
      </div>

      <!-- Modified tables -->
      <div v-if="diff.modified_tables.length" class="mb-6">
        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-yellow-500">Modified Tables</h3>
        <div class="space-y-2">
          <div v-for="m in diff.modified_tables" :key="m.table.name" class="rounded-md border border-yellow-500/20 bg-yellow-500/5 p-3">
            <div class="flex items-center gap-2">
              <Pencil class="h-3.5 w-3.5 text-yellow-500" />
              <span class="text-sm font-medium text-foreground">{{ m.table.name }}</span>
            </div>
            <div v-if="m.column_changes.length" class="mt-2 space-y-1 pl-5">
              <div v-for="c in m.column_changes" :key="c.column" class="flex items-center gap-2 text-xs">
                <component :is="changeIcon(c.type)" :class="changeColor(c.type)" class="h-3 w-3 shrink-0" />
                <span :class="changeColor(c.type)">{{ c.column }}</span>
                <template v-if="c.type === 'modified' && c.diffs">
                  <span class="text-muted-foreground">—</span>
                  <span v-for="d in c.diffs" :key="d.field" class="text-muted-foreground">
                    {{ d.field }}: <span class="text-red-500 line-through">{{ d.from }}</span>
                    <span class="mx-0.5">→</span>
                    <span class="text-green-500">{{ d.to }}</span>
                  </span>
                </template>
              </div>
            </div>
            <div v-if="m.index_changes.length" class="mt-2 space-y-1 pl-5 border-t border-border/50 pt-2">
              <div v-for="ix in m.index_changes" :key="ix.index" class="flex items-center gap-2 text-xs">
                <component :is="changeIcon(ix.type)" :class="changeColor(ix.type)" class="h-3 w-3 shrink-0" />
                <span :class="changeColor(ix.type)">{{ ix.type === 'added' ? '+' : '-' }} index: {{ ix.index }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No changes -->
      <div v-if="!diff.new_tables.length && !diff.deleted_tables.length && !diff.modified_tables.length" class="flex flex-col items-center gap-2 py-12 text-center">
        <ArrowLeftRight class="h-8 w-8 text-muted-foreground/50" />
        <p class="text-sm text-muted-foreground">No changes between these snapshots</p>
      </div>
    </div>
  </div>
</template>
