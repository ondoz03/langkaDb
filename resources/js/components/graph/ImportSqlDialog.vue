<script setup lang="ts">
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { Upload, X } from 'lucide-vue-next'

interface ImportedTable {
  name: string
  columns: { name: string; type: string }[]
  indexes: { name: string; columns: string[]; type: string }[]
  row_count: number
  size_mb: number
}

interface ImportedRelation {
  name: string
  from_table: string
  from_column: string
  to_table: string
  to_column: string
  type: string
}

interface ImportResult {
  tables: ImportedTable[]
  relations: ImportedRelation[]
  summary: { total_tables: number; total_relations: number; total_indexes: number }
}

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  imported: [result: ImportResult]
}>()

const sqlInput = ref('')
const loading = ref(false)
const result = ref<ImportResult | null>(null)

async function handleImport() {
  if (!sqlInput.value.trim()) return

  loading.value = true
  try {
    const res = await fetch('/api/schema/import-sql', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ sql: sqlInput.value }),
    })

    if (!res.ok) {
      const err = await res.json()
      throw new Error(err.message || 'Failed to parse SQL')
    }

    const json = await res.json()
    result.value = json.data

    toast.success(`Imported ${json.data.summary.total_tables} tables and ${json.data.summary.total_relations} relations`)
    emit('imported', json.data)
    close()
  } catch (e) {
    toast.error(e instanceof Error ? e.message : 'Import failed')
  } finally {
    loading.value = false
  }
}

function close() {
  sqlInput.value = ''
  result.value = null
  emit('close')
}
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    @click.self="close"
  >
    <div class="w-full max-w-2xl rounded-lg border border-border bg-card shadow-xl">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-border px-5 py-4">
        <div>
          <h2 class="text-sm font-semibold text-foreground">Import SQL DDL</h2>
          <p class="mt-0.5 text-xs text-muted-foreground">Paste CREATE TABLE statements to visualize them as a graph</p>
        </div>
        <button class="rounded-md p-1 text-muted-foreground hover:text-foreground" @click="close">
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-5">
        <textarea
          v-model="sqlInput"
          class="h-64 w-full rounded-md border border-border bg-black/20 p-3 font-mono text-xs text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
          placeholder="CREATE TABLE users (&#10;  id INT NOT NULL AUTO_INCREMENT,&#10;  name VARCHAR(255) NOT NULL,&#10;  email VARCHAR(255) NOT NULL,&#10;  PRIMARY KEY (id)&#10;);&#10;&#10;CREATE TABLE posts (&#10;  id INT NOT NULL AUTO_INCREMENT,&#10;  user_id INT NOT NULL,&#10;  title VARCHAR(255) NOT NULL,&#10;  PRIMARY KEY (id),&#10;  FOREIGN KEY (user_id) REFERENCES users(id)&#10;);"
          spellcheck="false"
        />
      </div>

      <!-- Footer -->
      <div class="flex items-center justify-end gap-2 border-t border-border px-5 py-3">
        <button
          class="rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground hover:text-foreground"
          @click="close"
        >
          Cancel
        </button>
        <button
          class="flex items-center gap-1.5 rounded-md bg-foreground px-3 py-1.5 text-xs font-medium text-background hover:opacity-90 disabled:opacity-50"
          :disabled="!sqlInput.trim() || loading"
          @click="handleImport"
        >
          <Upload class="h-3.5 w-3.5" />
          {{ loading ? 'Parsing...' : 'Import & Visualize' }}
        </button>
      </div>
    </div>
  </div>
</template>
