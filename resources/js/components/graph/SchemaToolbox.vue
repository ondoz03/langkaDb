<script setup lang="ts">
import { ref, computed } from 'vue'
import { Plus, Table2, GripVertical, X, Columns3, Pencil, Eye, EyeOff } from 'lucide-vue-next'
import type { TableData } from '@/composables/useGraph'
import CreateTableDialog from './CreateTableDialog.vue'

interface Props {
  tables: TableData[]
  canvasTableNames: string[]
}

const props = defineProps<Props>()

const emit = defineEmits<{
  addTable: [table: TableData]
  removeTable: [tableName: string]
  editTable: [table: TableData]
  createTable: [table: TableData]
}>()

const showDialog = ref(false)
const editingTable = ref<TableData | null>(null)
const searchQuery = ref('')

const filteredTables = computed(() => {
  if (!searchQuery.value) return props.tables
  const q = searchQuery.value.toLowerCase()
  return props.tables.filter(t => t.tableName.toLowerCase().includes(q))
})

const canvasSet = computed(() => new Set(props.canvasTableNames))

const existingTableNames = computed(() => props.tables.map(t => t.tableName))

const existingColumns = computed(() => {
  const map: Record<string, string[]> = {}
  for (const t of props.tables) {
    map[t.tableName] = t.columns.map(c => c.name)
  }
  return map
})

function handleCreateTable(data: TableData) {
  emit('createTable', data)
  showDialog.value = false
  editingTable.value = null
}

function openEditor(table: TableData) {
  editingTable.value = { ...table, columns: table.columns.map(c => ({ ...c })) }
  showDialog.value = true
}

function closeDialog() {
  showDialog.value = false
  editingTable.value = null
}

function onDragStart(e: DragEvent, table: TableData) {
  if (e.dataTransfer) {
    e.dataTransfer.setData('application/json', JSON.stringify(table))
    e.dataTransfer.effectAllowed = 'move'
  }
}
</script>

<template>
  <div class="flex h-full flex-col border-r border-border bg-card">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-3 py-2.5">
      <span class="text-xs font-medium text-foreground">Schema Toolbox</span>
      <span class="text-[10px] text-muted-foreground">{{ tables.length }} tables</span>
    </div>

    <!-- Search -->
    <div class="px-3 py-2">
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Search tables..."
        class="w-full rounded-md border border-border bg-black/20 px-2 py-1.5 text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
      />
    </div>

    <!-- Table list -->
    <div class="flex-1 overflow-y-auto px-3 pb-3">
      <div class="space-y-1">
        <div
          v-for="table in filteredTables"
          :key="table.tableName"
          class="group flex items-center gap-1.5 rounded-md border border-border/50 px-2 py-1.5 text-xs transition-colors hover:border-accent-brand/30 hover:bg-accent/30"
          :class="{ 'border-accent-brand/30 bg-accent-brand/5': canvasSet.has(table.tableName) }"
          draggable="true"
          @dragstart="onDragStart($event, table)"
          @click="emit('addTable', table)"
        >
          <GripVertical class="h-3 w-3 shrink-0 text-muted-foreground/30" />
          <div class="flex h-3.5 w-3.5 shrink-0 items-center justify-center">
            <div
              class="h-2.5 w-2.5 rounded-sm border"
              :class="canvasSet.has(table.tableName) ? 'bg-accent-brand border-accent-brand' : 'border-muted-foreground/30'"
            />
          </div>
          <span class="flex-1 truncate text-foreground">{{ table.tableName }}</span>
          <span class="shrink-0 text-[10px] text-muted-foreground">{{ table.columns.length }} cols</span>

          <button
            class="hidden shrink-0 rounded p-0.5 text-muted-foreground/50 hover:text-foreground group-hover:inline-flex"
            title="Edit table"
            @click.stop="openEditor(table)"
          >
            <Pencil class="h-3 w-3" />
          </button>
          <button
            v-if="canvasSet.has(table.tableName)"
            class="hidden shrink-0 rounded p-0.5 text-muted-foreground/50 hover:text-red-500 group-hover:inline-flex"
            title="Remove from graph"
            @click.stop="emit('removeTable', table.tableName)"
          >
            <X class="h-3 w-3" />
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="tables.length === 0" class="mt-6 text-center">
        <Table2 class="mx-auto h-8 w-8 text-muted-foreground/30" />
        <p class="mt-2 text-xs text-muted-foreground">No tables loaded</p>
        <p class="mt-0.5 text-[10px] text-muted-foreground/60">Import SQL or connect to a database</p>
      </div>
    </div>

    <!-- Create table button -->
    <div class="border-t border-border px-3 py-2">
      <button
        class="flex w-full items-center justify-center gap-1.5 rounded-md border border-dashed border-border py-2 text-xs text-muted-foreground transition-colors hover:border-accent-brand/50 hover:text-accent-brand"
        @click="showDialog = true; editingTable = null"
      >
        <Plus class="h-3.5 w-3.5" />
        New Table
      </button>
    </div>
  </div>

  <CreateTableDialog
    :open="showDialog"
    :edit-table="editingTable"
    :existing-tables="existingTableNames"
    :existing-columns="existingColumns"
    @close="closeDialog"
    @create="handleCreateTable"
  />
</template>
