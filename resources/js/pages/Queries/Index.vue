<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import ExplainTree from '@/components/query/ExplainTree.vue'
import { useConnectionStore } from '@/stores/connection'
import { Terminal, Play, Eraser, BarChart3, AlertTriangle } from 'lucide-vue-next'

const store = useConnectionStore()
const sql = ref('SELECT * FROM users LIMIT 10;')
const running = ref(false)
const result = ref<{ columns: string[]; rows: Record<string, unknown>[]; count: number } | null>(null)
const error = ref<string | null>(null)
const explainResult = ref<any>(null)
const explainLoading = ref(false)
const showExplain = ref(false)

async function runQuery() {
  if (!store.activeConnection || !sql.value.trim()) return
  running.value = true; result.value = null; error.value = null
  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/query`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ sql: sql.value }),
    })
    const json = await res.json()
    if (json.data) result.value = json.data
    else error.value = json.message ?? 'Query failed'
  } catch { error.value = 'Failed to execute query' }
  finally { running.value = false }
}

async function explainQuery() {
  if (!store.activeConnection || !sql.value.trim()) return
  explainLoading.value = true; explainResult.value = null; error.value = null; showExplain.value = true
  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/explain`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ sql: sql.value }),
    })
    const json = await res.json()
    if (json.data) explainResult.value = json.data
    else { error.value = json.message ?? 'EXPLAIN failed'; showExplain.value = false }
  } catch { error.value = 'Failed to run EXPLAIN'; showExplain.value = false }
  finally { explainLoading.value = false }
}
</script>

<template>
  <Head title="Query Analyzer" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">Query Analyzer</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Run SQL queries and analyze performance</p>
      </div>
      <div v-if="store.activeConnection" class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground">
        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
        {{ store.activeConnection.name }}
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!store.activeConnection" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Terminal class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No active connection</p>
          <p class="mt-1 text-sm text-muted-foreground">Connect to a database first to run queries.</p>
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Editor -->
      <div class="p-6 pb-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs text-muted-foreground">SQL Query</span>
          <div class="flex items-center gap-2">
            <Button size="sm" variant="outline" class="h-7 text-xs" @click="sql = ''"><Eraser class="mr-1 h-3.5 w-3.5" />Clear</Button>
            <Button size="sm" variant="outline" class="h-7 text-xs" :disabled="explainLoading || !sql.trim()" @click="explainQuery">
              <BarChart3 v-if="!explainLoading" class="mr-1 h-3.5 w-3.5" />
              <Spinner v-if="explainLoading" class="mr-1 h-3 w-3" />Explain
            </Button>
            <Button size="sm" class="h-7 text-xs" :disabled="running || !sql.trim()" @click="runQuery">
              <Play v-if="!running" class="mr-1 h-3.5 w-3.5" />
              <Spinner v-if="running" class="mr-1 h-3 w-3" />Run
            </Button>
          </div>
        </div>
        <textarea
          v-model="sql"
          class="h-32 w-full resize-y rounded-lg border border-border bg-card p-3 text-xs font-mono text-foreground outline-none transition-colors placeholder:text-muted-foreground/30 focus:border-ring"
          :class="{ 'border-red-500': error }"
          placeholder="Enter SQL query..."
          spellcheck="false"
          @keydown.meta.enter="runQuery"
          @keydown.ctrl.enter="runQuery"
        />
      </div>

      <!-- Results -->
      <div class="space-y-3 px-6 pb-6">
        <div v-if="error" class="flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-500">
          <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
          <span>{{ error }}</span>
        </div>

        <ExplainTree v-if="showExplain && explainResult" :result="explainResult" :loading="explainLoading" />

        <div v-if="running" class="flex items-center justify-center gap-2 rounded-lg border border-border bg-card py-12 text-sm text-muted-foreground">
          <Spinner /> Executing...
        </div>

        <div v-else-if="result" class="rounded-lg border border-border bg-card overflow-hidden">
          <div class="border-b border-border bg-muted/30 px-4 py-2 text-xs text-muted-foreground">{{ result.count }} rows returned</div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border bg-muted/20">
                  <th v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-left text-xs font-medium text-muted-foreground font-mono">{{ col }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-border last:border-0 hover:bg-accent/20">
                  <td v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-sm text-muted-foreground font-mono">{{ row[col] ?? 'NULL' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else class="flex items-center justify-center rounded-lg border border-dashed border-border py-16 text-sm text-muted-foreground">
          Write a query and click Run
        </div>
      </div>
    </template>
  </div>
</template>
