<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref, computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { useConnectionStore } from '@/stores/connection'
import { useAsyncJob } from '@/composables/useAsyncJob'
import { Lightbulb, Sparkles, History, Trash2, Search, AlertTriangle, CircleOff, RefreshCw } from 'lucide-vue-next'

interface Analysis {
  id: number
  connection_id: string
  connection_name: string
  provider: string
  score: number
  timestamp: string
  result: { findings: { severity: string; message: string }[]; recommendations: { priority: string; message: string }[] }
}

const store = useConnectionStore()
const allHistory = ref<Analysis[]>([])
const latestResult = ref<Analysis | null>(null)
const loading = ref(false)
const analyzing = ref(false)
const showHistory = ref(false)
const historySearch = ref('')
const { jobStatus, jobResult, error: jobError, startPolling, reset: resetJob } = useAsyncJob()

// Only show analyses for the active connection
const activeHistory = computed(() => {
  if (!store.activeConnectionId) return []
  return allHistory.value.filter(h => h.connection_id === store.activeConnectionId)
})

const jobProgressText = computed(() => {
  if (!jobStatus.value) return ''
  switch (jobStatus.value.status) {
    case 'pending': return 'Queued...'
    case 'processing': return `Processing... ${jobStatus.value.progress}%`
    case 'completed': return 'Complete!'
    case 'failed': return `Failed: ${jobError.value ?? 'Unknown error'}`
    default: return 'Waiting...'
  }
})

const filteredHistory = computed(() => {
  const items = activeHistory.value
  if (!historySearch.value) return items
  const q = historySearch.value.toLowerCase()
  return items.filter(h =>
    h.connection_name.toLowerCase().includes(q) ||
    h.provider.toLowerCase().includes(q)
  )
})

onMounted(() => {
  if (store.activeConnection) fetchHistory()
})

// Refetch when active connection changes
watch(() => store.activeConnectionId, (id) => {
  if (id) fetchHistory()
  else {
    allHistory.value = []
    latestResult.value = null
  }
})

// Watch async job completion
watch(() => jobResult.value, (result) => {
  if (result?.status === 'completed') {
    // Delay slightly so DB write completes
    setTimeout(() => fetchHistory(), 500)
  }
})

async function fetchHistory() {
  if (!store.activeConnectionId) return
  try {
    const res = await fetch(`/api/ai/analyses?connection_id=${store.activeConnectionId}`)
    const json = await res.json()
    if (json.data) {
      allHistory.value = json.data
      // Always set to the newest (first in array since sorted desc)
      if (json.data.length > 0) {
        latestResult.value = json.data[0]
      }
    }
  } catch { /* silent */ }
}

async function analyze() {
  if (!store.activeConnection || analyzing.value) return

  analyzing.value = true; loading.value = true; resetJob()
  try {
    const apiKey = ''  // rule-based doesn't need API key
    const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''

    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/analyze-async`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ api_key: apiKey, provider: 'rule', system_prompt: systemPrompt, connection_name: store.activeConnection.name }),
    })
    const json = await res.json()
    if (json.data?.job_id) startPolling(json.data.job_id, 2000)
  } catch { loading.value = false; analyzing.value = false }
}

// Also watch for job completion to reset analyzing state
watch(() => jobStatus.value?.status, (status) => {
  if (status === 'completed' || status === 'failed') {
    analyzing.value = false
    loading.value = false
  }
})

async function deleteAnalysis(id: number) {
  await fetch(`/api/ai/analyses/${id}`, { method: 'DELETE' })
  allHistory.value = allHistory.value.filter(h => h.id !== id)
  if (latestResult.value?.id === id) {
    const remaining = activeHistory.value
    latestResult.value = remaining.length > 0 ? remaining[0] : null
  }
}

function severityColor(s: string) {
  switch (s) {
    case 'high': return 'bg-red-500/10 text-red-600 dark:text-red-400'
    case 'medium': return 'bg-amber-500/10 text-amber-600 dark:text-amber-400'
    case 'low': return 'bg-blue-500/10 text-blue-600 dark:text-blue-400'
    default: return 'bg-muted text-muted-foreground'
  }
}

function selectAnalysis(a: Analysis) {
  latestResult.value = a
  showHistory.value = false
}
</script>

<template>
  <Head title="AI Insights" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">AI Insights</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Schema analysis and recommendations</p>
      </div>
      <div class="flex items-center gap-2">
        <Button size="sm" variant="outline" class="text-xs" :disabled="activeHistory.length === 0" @click="showHistory = true">
          <History class="mr-1.5 h-4 w-4" />
          History ({{ activeHistory.length }})
        </Button>
        <Button size="sm" class="text-xs" :disabled="!store.activeConnection || analyzing" @click="analyze">
          <Spinner v-if="analyzing" class="mr-1.5 h-4 w-4" />
          <Sparkles v-else class="mr-1.5 h-4 w-4" />
          {{ analyzing ? 'Analyzing...' : 'Analyze Schema' }}
        </Button>
      </div>
    </div>

    <!-- Async job progress -->
    <div v-if="jobStatus && jobStatus.status !== 'completed' && jobStatus.status !== 'failed'" class="mx-6 mt-4 rounded-lg border border-border bg-card p-4">
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Spinner />
        <span>{{ jobProgressText }}</span>
      </div>
      <div v-if="jobStatus.progress > 0" class="mt-2 h-1 w-full overflow-hidden rounded-full bg-muted">
        <div class="h-full rounded-full bg-foreground/20 transition-all" :style="{ width: jobStatus.progress + '%' }" />
      </div>
    </div>

    <!-- Error -->
    <div v-if="jobError" class="mx-6 mt-4 flex items-start gap-3 rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-500">
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      {{ jobError }}
    </div>

    <!-- No active connection -->
    <div v-if="!store.activeConnection" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Lightbulb class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No active connection</p>
          <p class="mt-1 text-sm text-muted-foreground">Connect to a database to get AI-powered insights.</p>
        </div>
      </div>
    </div>

    <!-- No analysis -->
    <div v-else-if="activeHistory.length === 0 && !loading" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Sparkles class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No analysis yet</p>
          <p class="mt-1 text-sm text-muted-foreground">Click "Analyze Schema" to get AI insights for <span class="font-medium text-foreground/80">{{ store.activeConnection?.name }}</span>.</p>
        </div>
        <Button variant="accent" size="sm" :disabled="analyzing" @click="analyze">
          <Sparkles class="mr-1.5 h-4 w-4" />
          Analyze Schema
        </Button>
      </div>
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground">
      <Spinner /> Analyzing with AI...
    </div>

    <!-- Results -->
    <template v-else-if="latestResult">
      <div class="flex items-center gap-3 px-6 pt-4 pb-2 text-sm text-muted-foreground">
        <span class="font-medium text-foreground">{{ latestResult.connection_name }}</span>
        <span>·</span>
        <span :class="latestResult.score >= 80 ? 'text-green-500' : latestResult.score >= 60 ? 'text-amber-500' : 'text-red-500'">
          Score: {{ latestResult.score }}/100
        </span>
        <span>·</span>
        <span>{{ latestResult.timestamp }}</span>
        <span v-if="(latestResult as any)?.tokens" class="text-muted-foreground/50">
          · {{ ((latestResult as any)?.tokens?.total ?? 0).toLocaleString() }} tokens
        </span>
      </div>

      <div class="grid gap-2 p-6 pt-3 md:grid-cols-2">
        <div v-for="f in (latestResult.result?.findings ?? [])" :key="f.message" class="rounded-lg border border-border bg-card p-4">
          <span class="inline-block rounded px-2 py-0.5 text-xs font-medium" :class="severityColor(f.severity)">{{ f.severity }}</span>
          <p class="mt-2 text-sm text-foreground">{{ f.message }}</p>
        </div>
      </div>

      <div v-if="latestResult.result?.recommendations?.length" class="mx-6 mb-6 rounded-lg border border-border bg-card p-4">
        <p class="text-sm font-medium text-foreground mb-3">Recommendations</p>
        <div class="divide-y divide-border">
          <div v-for="r in latestResult.result.recommendations" :key="r.message" class="flex items-start gap-3 py-2.5 first:pt-0 last:pb-0">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="r.priority === 'high' ? 'bg-red-500' : r.priority === 'medium' ? 'bg-amber-500' : 'bg-blue-500'" />
            <p class="text-sm text-foreground">{{ r.message }}</p>
          </div>
        </div>
      </div>

      <!-- Refresh hint -->
      <div class="flex items-center justify-center gap-1.5 pb-6 text-xs text-muted-foreground/40">
        <RefreshCw class="h-3 w-3" />
        <span>Analyses are loaded for <span class="font-medium text-foreground/50">{{ store.activeConnection?.name }}</span></span>
      </div>
    </template>
  </div>

  <!-- History Modal -->
  <Dialog :open="showHistory" @update:open="showHistory = $event">
    <DialogContent class="max-w-xl p-0 overflow-hidden">
      <div class="border-b border-border px-5 py-4">
        <DialogTitle class="text-base font-semibold text-foreground">
          Analysis History
        </DialogTitle>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ activeHistory.length }} analysis{{ activeHistory.length !== 1 ? 'es' : '' }} for <span class="font-medium text-foreground/70">{{ store.activeConnection?.name }}</span>
        </p>
      </div>

      <!-- Search -->
      <div v-if="activeHistory.length > 5" class="px-5 pt-3">
        <div class="relative">
          <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            v-model="historySearch"
            type="text"
            placeholder="Filter by provider..."
            class="w-full rounded-md border border-border bg-muted/30 py-2 pl-10 pr-3 text-sm text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-ring"
          />
        </div>
      </div>

      <div class="max-h-80 overflow-y-auto px-5 py-3">
        <div v-if="filteredHistory.length === 0" class="flex flex-col items-center gap-3 py-8 text-center">
          <CircleOff class="h-8 w-8 text-muted-foreground/30" />
          <p class="text-sm text-muted-foreground">
            {{ activeHistory.length === 0 ? 'No analyses yet for this connection' : 'No matches found' }}
          </p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(h, i) in filteredHistory"
            :key="h.id"
            class="group flex cursor-pointer items-center gap-4 rounded-lg border p-3.5 transition-colors"
            :class="latestResult?.id === h.id
              ? 'border-border bg-accent/50'
              : 'border-border/60 bg-card hover:bg-accent/30'"
            @click="selectAnalysis(h)"
          >
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border text-xs text-muted-foreground font-mono">
              {{ i + 1 }}
            </span>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="text-sm font-medium text-foreground truncate">{{ h.connection_name }}</p>
                <span class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground font-mono">{{ h.provider }}</span>
              </div>
              <p class="mt-0.5 text-xs text-muted-foreground">{{ h.timestamp }}</p>
            </div>

            <div
              class="flex h-8 w-12 shrink-0 items-center justify-center rounded border text-xs font-semibold font-mono"
              :class="h.score >= 80
                ? 'border-green-500/20 bg-green-500/5 text-green-600 dark:text-green-400'
                : h.score >= 60
                  ? 'border-amber-500/20 bg-amber-500/5 text-amber-600 dark:text-amber-400'
                  : 'border-red-500/20 bg-red-500/5 text-red-600 dark:text-red-400'"
            >
              {{ h.score }}
            </div>

            <button
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded text-muted-foreground/30 opacity-0 transition-all hover:bg-red-500/10 hover:text-red-500 group-hover:opacity-100"
              @click.stop="deleteAnalysis(h.id)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>

      <div class="border-t border-border px-5 py-3 text-xs text-muted-foreground">
        {{ filteredHistory.length }} of {{ activeHistory.length }} analyses
      </div>
    </DialogContent>
  </Dialog>
</template>
