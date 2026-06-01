<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { getKey } from '@/modules/ai/apiKeys'
import { MODELS } from '@/modules/ai/config'
import { useConnectionStore } from '@/stores/connection'
import { useAsyncJob } from '@/composables/useAsyncJob'

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
const { jobStatus, jobResult, error: jobError, startPolling, reset: resetJob } = useAsyncJob()

// Show async job progress
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

onMounted(fetchHistory)

async function fetchHistory() {
  try {
    const res = await fetch('/api/ai/analyses')
    const json = await res.json()

    if (json.data) {
      allHistory.value = json.data

      if (!latestResult.value && json.data.length > 0) {
        latestResult.value = json.data[0]
      }
    }
  } catch {
    // silent
  }
}

async function analyze() {
  if (!store.activeConnection || analyzing.value) {
return
}

  const now = Date.now()
  const lastSent = parseInt(sessionStorage.getItem('ai_analyze_at') ?? '0', 10)

  if (now - lastSent < 10_000) {
return
}

  sessionStorage.setItem('ai_analyze_at', String(now))

  analyzing.value = true
  loading.value = true
  resetJob()

  try {
    const modelId = localStorage.getItem('aetherdb_default_model') ?? ''
    const model = MODELS.find(m => m.id === modelId)
    const provider = model?.provider ?? 'openai'
    const apiKey = getKey(provider) ?? ''
    const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''

    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/analyze-async`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ api_key: apiKey, provider, system_prompt: systemPrompt, connection_name: store.activeConnection.name }),
    })

    const json = await res.json()

    if (json.data?.job_id) {
      startPolling(json.data.job_id, 2000)
    }

    // Still fetch history after a delay to get the new result
    setTimeout(async () => {
      await fetchHistory()
      loading.value = false
      analyzing.value = false
    }, 3000)
  } catch {
    loading.value = false
    analyzing.value = false
  }
}

async function deleteAnalysis(id: number) {
  await fetch(`/api/ai/analyses/${id}`, { method: 'DELETE' })
  allHistory.value = allHistory.value.filter(h => h.id !== id)

  if (latestResult.value?.id === id) {
    latestResult.value = allHistory.value[0] ?? null
  }
}

function severityColor(s: string) {
  switch (s) {
    case 'high': return 'bg-red-500/10 text-red-500'
    case 'medium': return 'bg-amber-500/10 text-amber-500'
    case 'low': return 'bg-blue-500/10 text-blue-500'
    default: return 'bg-muted text-muted-foreground'
  }
}
</script>

<template>
  <Head title="AI Insights" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 font-mono">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">AI Insights</h2>
      <div class="flex items-center gap-2">
        <Button size="sm" variant="outline" :disabled="allHistory.length === 0" @click="showHistory = true">
          History ({{ allHistory.length }})
        </Button>
        <Button size="sm" :disabled="!store.activeConnection || analyzing" @click="analyze">
          <Spinner v-if="analyzing" />
          {{ analyzing ? 'Analyzing...' : 'Analyze Schema' }}
        </Button>
      </div>
    </div>

    <!-- Async job progress indicator -->
    <div
      v-if="jobStatus && jobStatus.status !== 'completed' && jobStatus.status !== 'failed'"
      class="border border-border bg-card p-3"
    >
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <Spinner />
        <span>{{ jobProgressText }}</span>
      </div>
      <div v-if="jobStatus.progress > 0" class="mt-2 h-1 w-full bg-muted">
        <div
          class="h-1 bg-primary transition-all"
          :style="{ width: jobStatus.progress + '%' }"
        />
      </div>
    </div>

    <!-- Job error -->
    <div
      v-if="jobError"
      class="border border-red-500/30 bg-red-500/5 p-3 text-xs text-red-500"
    >
      {{ jobError }}
    </div>

    <div v-if="!store.activeConnection" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
      No active connection
    </div>

    <div v-else-if="!latestResult && !loading" class="flex flex-1 items-center justify-center">
      <div class="text-center">
        <p class="text-sm text-muted-foreground">No analysis yet</p>
        <p class="mt-1 text-xs text-muted-foreground">Click "Analyze Schema" to get AI insights about your database</p>
      </div>
    </div>

    <div v-else-if="loading" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
      Analyzing with AI...
    </div>

    <template v-else-if="latestResult">
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <span>{{ latestResult.connection_name }}</span>
        <span>·</span>
        <span>Score: {{ latestResult.score }}/100</span>
        <span>·</span>
        <span>{{ latestResult.timestamp }}</span>
        <span v-if="(latestResult as any)?.tokens" class="text-muted-foreground">· {{ ((latestResult as any)?.tokens?.total ?? 0).toLocaleString() }} tokens</span>
      </div>

      <div class="grid gap-3 md:grid-cols-2">
        <div
          v-for="f in (latestResult.result?.findings ?? [])"
          :key="f.message"
          class="border border-border bg-card p-4"
        >
          <span class="inline-block px-1.5 py-0.5 text-[10px] font-medium" :class="severityColor(f.severity)">{{ f.severity }}</span>
          <p class="mt-2 text-xs text-foreground">{{ f.message }}</p>
        </div>
      </div>

      <div v-if="latestResult.result?.recommendations?.length" class="border border-border bg-card p-4">
        <h3 class="text-sm font-medium text-foreground">Recommendations</h3>
        <div class="mt-3 divide-y divide-border text-xs">
          <div
            v-for="r in latestResult.result.recommendations"
            :key="r.message"
            class="flex items-start gap-3 py-2"
          >
            <span
              class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full"
              :class="r.priority === 'high' ? 'bg-red-500' : r.priority === 'medium' ? 'bg-amber-500' : 'bg-blue-500'"
            />
            <div>
              <p class="text-foreground">{{ r.message }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>

  <Dialog :open="showHistory" @update:open="showHistory = false">
    <DialogContent class="max-w-lg font-mono">
      <DialogHeader>
        <DialogTitle class="font-mono">Analysis History</DialogTitle>
      </DialogHeader>

      <div class="flex flex-col gap-2">
        <div
          v-for="(h, i) in allHistory"
          :key="h.id"
          class="flex cursor-pointer items-center justify-between border border-border bg-card p-3 hover:bg-accent/30"
          :class="{ 'border-primary': latestResult?.id === h.id }"
          @click="latestResult = h; showHistory = false"
        >
          <div class="flex items-center gap-3">
            <span class="text-xs text-muted-foreground">{{ i + 1 }}.</span>
            <div>
              <span class="text-xs font-medium text-foreground">{{ h.connection_name }}</span>
              <span class="ml-2 text-[10px] text-muted-foreground">{{ h.timestamp }}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-[10px] text-muted-foreground">Score: {{ h.score }}/100</span>
            <button class="text-[10px] text-muted-foreground hover:text-red-500" @click.stop="deleteAnalysis(h.id)">×</button>
          </div>
        </div>

        <div v-if="allHistory.length === 0" class="py-8 text-center text-xs text-muted-foreground">
          No history yet
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
