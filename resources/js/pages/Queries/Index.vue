<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import ExplainTree from '@/components/query/ExplainTree.vue'
import { useConnectionStore } from '@/stores/connection'

const store = useConnectionStore()
const sql = ref('SELECT * FROM users LIMIT 10;')
const running = ref(false)
const result = ref<{ columns: string[]; rows: Record<string, unknown>[]; count: number } | null>(null)
const error = ref<string | null>(null)
const explainResult = ref<any>(null)
const explainLoading = ref(false)
const showExplain = ref(false)

async function runQuery() {
  if (!store.activeConnection || !sql.value.trim()) {
    return
  }

  running.value = true
  result.value = null
  error.value = null

  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/query`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ sql: sql.value }),
    })

    const json = await res.json()

    if (json.data) {
      result.value = json.data
    } else {
      error.value = json.message ?? 'Query failed'
    }
  } catch {
    error.value = 'Failed to execute query'
  } finally {
    running.value = false
  }
}

async function explainQuery() {
  if (!store.activeConnection || !sql.value.trim()) {
    return
  }

  explainLoading.value = true
  explainResult.value = null
  error.value = null
  showExplain.value = true

  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/explain`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ sql: sql.value }),
    })

    const json = await res.json()

    if (json.data) {
      explainResult.value = json.data
    } else {
      error.value = json.message ?? 'EXPLAIN failed'
      showExplain.value = false
    }
  } catch {
    error.value = 'Failed to run EXPLAIN'
    showExplain.value = false
  } finally {
    explainLoading.value = false
  }
}
</script>

<template>
  <Head title="Query Analyzer" />

  <div class="flex h-full flex-1 flex-col">
    <div class="flex items-center justify-between border-b border-border px-4 py-2">
      <h2 class="text-sm font-medium text-foreground">Query Analyzer</h2>
    </div>

    <div class="flex flex-1 flex-col gap-3 overflow-x-auto p-4">
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-xs text-muted-foreground">SQL Query</span>
          <div class="flex items-center gap-2">
            <Button size="sm" variant="outline" @click="sql = ''">Clear</Button>
            <Button size="sm" variant="outline" :disabled="explainLoading || !store.activeConnection" @click="explainQuery">
              <Spinner v-if="explainLoading" /> Explain
            </Button>
            <Button size="sm" :disabled="running || !store.activeConnection" @click="runQuery">
              <Spinner v-if="running" /> Run
            </Button>
          </div>
        </div>
        <textarea v-model="sql" class="h-28 resize-none rounded-lg border border-border bg-card p-3 text-xs text-foreground outline-none font-mono focus:border-accent-brand/50 focus:ring-1 focus:ring-accent-brand/20 transition-colors" placeholder="Enter SQL query..." spellcheck="false" />
      </div>

      <div v-if="running" class="flex items-center justify-center py-8 text-xs text-muted-foreground">Executing...</div>

      <div v-else-if="error" class="border border-red-500/30 bg-red-500/5 p-3 text-xs text-red-500">{{ error }}</div>

      <ExplainTree
        v-if="showExplain && explainResult"
        :result="explainResult"
        :loading="explainLoading"
        class="mb-3"
      />

      <div v-else-if="result" class="border border-border bg-card">
        <div class="border-b border-border bg-muted/30 px-3 py-1.5 text-xs font-medium text-foreground">{{ result.count }} rows returned</div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-border bg-muted/20">
                <th v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-left font-medium text-foreground">{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-border last:border-0 hover:bg-accent/20">
                <td v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ row[col] ?? 'NULL' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="!store.activeConnection" class="flex flex-1 items-center justify-center text-xs text-muted-foreground">Connect a database first</div>

      <div v-else class="flex flex-1 items-center justify-center text-xs text-muted-foreground">Write a query and click Run</div>
    </div>
  </div>
</template>
