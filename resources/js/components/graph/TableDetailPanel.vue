<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import type { TableData } from '@/composables/useGraph'

interface Props {
  open: boolean
  table: TableData | null
}

defineProps<Props>()

const emit = defineEmits<{
  close: []
}>()

function typeBadge(type: string) {
  switch (type) {
    case 'primary': return 'bg-amber-500/10 text-amber-500'
    case 'unique': return 'bg-blue-500/10 text-blue-500'
    default: return 'bg-muted text-muted-foreground'
  }
}
</script>

<template>
  <Sheet :open="open" @update:open="emit('close')">
    <SheetContent class="w-[400px] border-l border-border font-mono sm:max-w-md">
      <SheetHeader>
        <SheetTitle v-if="table" class="font-mono">{{ table.tableName }}</SheetTitle>
        <SheetDescription v-if="table" class="font-mono text-xs text-muted-foreground">
          {{ table.columns?.length ?? 0 }} columns
          <template v-if="table.rowCount > 0"> · {{ table.rowCount.toLocaleString() }} rows</template>
          <template v-if="table.sizeKb > 0"> · {{ (table.sizeKb / 1000).toFixed(1) }} MB</template>
        </SheetDescription>
      </SheetHeader>

      <div v-if="table" class="mt-4 grid gap-4">
        <div>
          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Columns</h3>
          <div class="divide-y divide-border border border-border">
            <div
              v-for="col in (table.columns ?? [])"
              :key="col.name"
              class="flex items-center gap-2 px-3 py-2 text-xs"
            >
              <div
                class="h-2 w-2 rounded-full"
                :class="col.primary ? 'bg-amber-500' : 'bg-muted-foreground/30'"
              />
              <span class="flex-1 font-medium" :class="{ 'text-amber-500': col.primary }">
                {{ col.name }}
              </span>
              <span class="text-muted-foreground">{{ col.type }}</span>
              <span v-if="col.nullable" class="text-xs text-muted-foreground/50">NULL</span>
              <span v-if="col.default !== null && col.default !== undefined" class="text-xs text-muted-foreground/50">[{{ col.default }}]</span>
            </div>
          </div>
        </div>

        <div v-if="table.indexes && table.indexes.length > 0">
          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Indexes</h3>
          <div class="divide-y divide-border border border-border">
            <div
              v-for="idx in table.indexes"
              :key="idx.name"
              class="px-3 py-2 text-xs"
            >
              <div class="flex items-center gap-2">
                <span class="font-medium">{{ idx.name }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium" :class="typeBadge(idx.type)">{{ idx.type }}</span>
              </div>
              <p class="mt-0.5 text-muted-foreground">{{ idx.columns.join(', ') }}</p>
            </div>
          </div>
        </div>
      </div>
    </SheetContent>
  </Sheet>
</template>
