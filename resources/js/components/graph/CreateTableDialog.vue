<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Plus, X, Trash2, GripVertical, Link2 } from 'lucide-vue-next'
import type { TableData } from '@/composables/useGraph'

interface ColumnRow {
  id: number
  name: string
  type: string
  nullable: boolean
  primary: boolean
}

interface ForeignKeyRow {
  id: number
  column: string
  referencesTable: string
  referencesColumn: string
  onDelete: string
}

const props = defineProps<{
  open: boolean
  editTable?: TableData | null
  existingTables?: string[]
  existingColumns?: Record<string, string[]>
}>()

const emit = defineEmits<{
  close: []
  create: [table: TableData]
}>()

const tableName = ref('')
const columns = ref<ColumnRow[]>([{ id: 1, name: '', type: 'VARCHAR', nullable: true, primary: false }])
const foreignKeys = ref<ForeignKeyRow[]>([])
let nextColId = 2
let nextFkId = 1

const TYPES = ['VARCHAR', 'INT', 'BIGINT', 'TINYINT', 'TEXT', 'BOOLEAN', 'DATETIME', 'DATE', 'FLOAT', 'DECIMAL', 'JSON', 'BLOB', 'CHAR']

const allTableNames = computed(() => props.existingTables ?? [])

const columnsForTable = computed(() => {
  const map: Record<string, string[]> = {}
  if (props.existingColumns) {
    Object.assign(map, props.existingColumns)
  }
  return map
})

watch(() => props.open, (val) => {
  if (val && props.editTable) {
    tableName.value = props.editTable.tableName
    columns.value = props.editTable.columns.map((c, i) => ({
      id: i + 1,
      name: c.name,
      type: c.type,
      nullable: c.nullable,
      primary: c.primary,
    }))
    nextColId = columns.value.length + 1
    foreignKeys.value = (props.editTable.foreignKeys ?? []).map((fk, i) => ({
      id: i + 1,
      column: fk.column,
      referencesTable: fk.referencesTable,
      referencesColumn: fk.referencesColumn,
      onDelete: fk.onDelete ?? 'RESTRICT',
    }))
    nextFkId = foreignKeys.value.length + 1
  } else if (val) {
    tableName.value = ''
    columns.value = [{ id: 1, name: '', type: 'VARCHAR', nullable: true, primary: false }]
    foreignKeys.value = []
    nextColId = 2
    nextFkId = 1
  }
})

function addColumn() {
  columns.value.push({ id: nextColId++, name: '', type: 'VARCHAR', nullable: true, primary: false })
}

function removeColumn(id: number) {
  if (columns.value.length <= 1) return
  columns.value = columns.value.filter(c => c.id !== id)
}

function addForeignKey() {
  foreignKeys.value.push({ id: nextFkId++, column: '', referencesTable: '', referencesColumn: '', onDelete: 'RESTRICT' })
}

function removeForeignKey(id: number) {
  foreignKeys.value = foreignKeys.value.filter(fk => fk.id !== id)
}

function handleCreate() {
  if (!tableName.value.trim() || columns.value.length === 0) return

  const cols = columns.value.map((c, i) => ({
    name: c.name || `column_${i + 1}`,
    type: c.type,
    nullable: c.nullable && !c.primary,
    default: null,
    primary: c.primary,
    comment: null,
  }))

  const primaryCols = cols.filter(c => c.primary).map(c => c.name)

  const table: TableData = {
    tableName: tableName.value.trim(),
    columns: cols,
    indexes: primaryCols.length > 0 ? [{ name: 'PRIMARY', columns: primaryCols, unique: true, type: 'primary' }] : [],
    foreignKeys: foreignKeys.value.map(fk => ({
      column: fk.column,
      referencesTable: fk.referencesTable,
      referencesColumn: fk.referencesColumn,
      onDelete: fk.onDelete,
    })),
    rowCount: 0,
    sizeKb: 0,
  }

  emit('create', table)
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="emit('close')"
  >
    <div class="w-full max-w-lg rounded-lg border border-border bg-card shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-border px-5 py-4">
        <div>
          <h2 class="text-sm font-semibold text-foreground">{{ editTable ? 'Edit Table' : 'New Table' }}</h2>
          <p class="mt-0.5 text-xs text-muted-foreground">{{ editTable ? 'Modify table definition' : 'Define a new table to add to the schema' }}</p>
        </div>
        <button class="rounded-md p-1 text-muted-foreground hover:text-foreground" @click="emit('close')">
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Body -->
      <div class="max-h-[65vh] space-y-4 overflow-y-auto p-5">
        <!-- Table name -->
        <div>
          <label class="text-xs font-medium text-foreground">Table Name</label>
          <input
            v-model="tableName"
            type="text"
            placeholder="e.g. products"
            class="mt-1 w-full rounded-md border border-border bg-black/20 px-3 py-2 text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring font-mono"
          />
        </div>

        <!-- Columns -->
        <div>
          <div class="flex items-center justify-between">
            <label class="text-xs font-medium text-foreground">Columns</label>
            <button
              class="flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground"
              @click="addColumn"
            >
              <Plus class="h-3 w-3" />
              Add column
            </button>
          </div>

          <div class="mt-2 space-y-1.5">
            <div
              v-for="(col, i) in columns"
              :key="col.id"
              class="flex items-center gap-1.5 rounded-md border border-border/50 bg-black/10 px-2 py-1.5"
            >
              <GripVertical class="h-3 w-3 shrink-0 text-muted-foreground/30" />
              <input
                v-model="col.name"
                type="text"
                :placeholder="`column_${i + 1}`"
                class="min-w-0 flex-1 rounded border border-border/50 bg-transparent px-1.5 py-1 text-[11px] text-foreground outline-none placeholder:text-muted-foreground/30 focus:border-ring font-mono"
              />
              <select
                v-model="col.type"
                class="rounded border border-border/50 bg-transparent px-1 py-1 text-[10px] text-foreground outline-none focus:border-ring"
              >
                <option v-for="t in TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
              <label class="flex items-center gap-1 text-[10px] text-muted-foreground" title="Primary Key">
                <input type="checkbox" v-model="col.primary" class="h-3 w-3" />
                PK
              </label>
              <label class="flex items-center gap-1 text-[10px] text-muted-foreground" title="Nullable">
                <input type="checkbox" v-model="col.nullable" class="h-3 w-3" />
                N
              </label>
              <button
                v-if="columns.length > 1"
                class="shrink-0 rounded p-0.5 text-muted-foreground/50 hover:text-red-500"
                @click="removeColumn(col.id)"
              >
                <Trash2 class="h-3 w-3" />
              </button>
            </div>
          </div>
        </div>

        <!-- Foreign Keys -->
        <div>
          <div class="flex items-center justify-between">
            <label class="text-xs font-medium text-foreground">Foreign Keys</label>
            <button
              class="flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground"
              @click="addForeignKey"
            >
              <Link2 class="h-3 w-3" />
              Add relation
            </button>
          </div>

          <div v-if="foreignKeys.length === 0" class="mt-1 text-[10px] text-muted-foreground/50">
            No foreign keys defined. Add relations to connect tables.
          </div>

          <div class="mt-2 space-y-2">
            <div
              v-for="fk in foreignKeys"
              :key="fk.id"
              class="rounded-md border border-border/50 bg-black/10 p-2"
            >
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] font-medium text-foreground/70">Relation</span>
                <button
                  class="rounded p-0.5 text-muted-foreground/50 hover:text-red-500"
                  @click="removeForeignKey(fk.id)"
                >
                  <X class="h-3 w-3" />
                </button>
              </div>
              <div class="grid grid-cols-2 gap-1.5">
                <div>
                  <label class="text-[9px] text-muted-foreground">From Column</label>
                  <input
                    v-model="fk.column"
                    type="text"
                    placeholder="column name"
                    class="w-full rounded border border-border/50 bg-black/20 px-1.5 py-1 text-[10px] text-foreground outline-none placeholder:text-muted-foreground/30 focus:border-ring font-mono"
                  />
                </div>
                <div>
                  <label class="text-[9px] text-muted-foreground">References Table</label>
                  <input
                    v-model="fk.referencesTable"
                    type="text"
                    placeholder="table name"
                    list="existing-tables"
                    class="w-full rounded border border-border/50 bg-black/20 px-1.5 py-1 text-[10px] text-foreground outline-none placeholder:text-muted-foreground/30 focus:border-ring font-mono"
                  />
                </div>
                <div>
                  <label class="text-[9px] text-muted-foreground">References Column</label>
                  <input
                    v-model="fk.referencesColumn"
                    type="text"
                    placeholder="column name"
                    class="w-full rounded border border-border/50 bg-black/20 px-1.5 py-1 text-[10px] text-foreground outline-none placeholder:text-muted-foreground/30 focus:border-ring font-mono"
                  />
                </div>
                <div>
                  <label class="text-[9px] text-muted-foreground">On Delete</label>
                  <select
                    v-model="fk.onDelete"
                    class="w-full rounded border border-border/50 bg-black/20 px-1 py-1 text-[10px] text-foreground outline-none focus:border-ring"
                  >
                    <option value="RESTRICT">RESTRICT</option>
                    <option value="CASCADE">CASCADE</option>
                    <option value="SET NULL">SET NULL</option>
                    <option value="NO ACTION">NO ACTION</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <datalist id="existing-tables">
            <option v-for="t in allTableNames" :key="t" :value="t" />
          </datalist>
        </div>
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3">
        <button
          class="rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground"
          @click="emit('close')"
        >
          Cancel
        </button>
        <button
          class="rounded-md bg-foreground px-3 py-1.5 text-xs font-medium text-background hover:opacity-90 disabled:opacity-50"
          :disabled="!tableName.trim()"
          @click="handleCreate"
        >
          {{ editTable ? 'Update Table' : 'Add to Schema' }}
        </button>
      </div>
    </div>
  </div>
</template>
