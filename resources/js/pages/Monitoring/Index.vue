<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import { RefreshCw, Activity, AlertTriangle, Database, Wifi, WifiOff, Server, Cpu, HardDrive } from 'lucide-vue-next'

interface Conn {
  id: string; name: string; driver: string; host: string
  port: number; database: string; status: string
  created_at: string; updated_at: string
}

interface StatsData {
  total_connections: number; active_connections: number
  warning_connections: number; disconnected_connections: number
  slow_queries_24h: number; total_queries_24h: number
  avg_health_score: number; avg_health_grade: string
  connections: Conn[]
}

interface Alert {
  type: string; severity: string; message: string
  connection: string; connection_id: string; time: string
}

const loading = ref(true)
const error = ref('')
const stats = ref<StatsData | null>(null)
const alerts = ref<Alert[]>([])
const lastUpdated = ref('')

async function fetchData() {
  loading.value = true
  error.value = ''
  try {
    const [statsRes, alertsRes] = await Promise.all([
      fetch('/api/monitoring/stats', { credentials: 'include' }),
      fetch('/api/monitoring/alerts', { credentials: 'include' }),
    ])
    if (!statsRes.ok) throw new Error(`Stats API: ${statsRes.status}`)
    if (!alertsRes.ok) throw new Error(`Alerts API: ${alertsRes.status}`)
    const statsJson = await statsRes.json()
    const alertsJson = await alertsRes.json()
    if (statsJson.success) stats.value = statsJson.data
    if (alertsJson.success) alerts.value = alertsJson.data
    lastUpdated.value = new Date().toLocaleTimeString()
  } catch (e: any) {
    error.value = e.message || 'Failed to load monitoring data'
  } finally {
    loading.value = false
  }
}

const statCards = computed(() => {
  if (!stats.value) return []
  const s = stats.value
  return [
    { label: 'Total Connections', value: String(s.total_connections), sub: `${s.active_connections} active`, icon: Database },
    { label: 'Health Score', value: String(s.avg_health_score), sub: `Grade ${s.avg_health_grade}`, icon: Activity, accent: s.avg_health_score >= 80 },
    { label: 'Active', value: String(s.active_connections), sub: `${Math.round(s.active_connections / Math.max(s.total_connections, 1) * 100)}%`, icon: Wifi, accent: s.active_connections > 0 },
    { label: 'Warnings', value: String(s.warning_connections), sub: `${s.warning_connections} connections`, icon: AlertTriangle, accent: s.warning_connections > 0, warn: true },
    { label: 'Disconnected', value: String(s.disconnected_connections), sub: `${s.disconnected_connections} offline`, icon: WifiOff, accent: s.disconnected_connections > 0, warn: true },
    { label: 'Slow Queries', value: String(s.slow_queries_24h), sub: 'last 24h', icon: Cpu, accent: s.slow_queries_24h > 0, warn: true },
  ]
})

function dbStatusColor(s: string) {
  switch (s) {
    case 'connected': return 'text-green-600 dark:text-green-400'
    case 'warning': return 'text-amber-600 dark:text-amber-400'
    default: return 'text-red-600 dark:text-red-400'
  }
}

onMounted(fetchData)
</script>

<template>
  <Head title="Monitoring" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">Monitoring</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Database health and performance overview</p>
      </div>
      <button
        class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
        @click="fetchData"
      >
        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
        {{ loading ? 'Loading...' : 'Refresh' }}
      </button>
    </div>

    <div v-if="loading && !stats" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">Loading...</div>

    <div v-else-if="error" class="mx-6 mt-6 flex items-center gap-3 rounded-lg border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-500">
      <AlertTriangle class="h-4 w-4 shrink-0" />
      <span class="flex-1">{{ error }}</span>
      <button class="underline hover:text-red-400" @click="fetchData">Retry</button>
    </div>

    <template v-else-if="stats">
      <!-- Stat cards -->
      <div class="grid gap-3 p-6 pb-0 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <div
          v-for="s in statCards"
          :key="s.label"
          class="rounded-lg border border-border bg-card p-4"
        >
          <div class="flex items-start justify-between">
            <p class="text-xs text-muted-foreground">{{ s.label }}</p>
            <component :is="s.icon" class="h-4 w-4" :class="s.warn ? 'text-amber-500' : 'text-muted-foreground'" />
          </div>
          <p class="mt-2 text-2xl font-semibold text-foreground">{{ s.value }}</p>
          <p class="mt-0.5 text-xs" :class="s.accent && s.warn ? 'text-amber-500' : s.accent ? 'text-green-500' : 'text-muted-foreground'">{{ s.sub }}</p>
        </div>
      </div>

      <!-- Bottom grid -->
      <div class="grid gap-3 p-6 lg:grid-cols-2">
        <!-- Connection Status -->
        <div v-if="stats.connections" class="rounded-lg border border-border bg-card p-4">
          <p class="text-xs font-medium text-foreground">Connection Status</p>
          <div class="mt-3 divide-y divide-border">
            <div
              v-for="conn in stats.connections"
              :key="conn.id"
              class="flex items-center justify-between py-2.5 first:pt-0 last:pb-0"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded border border-border bg-muted/50 text-xs font-medium text-muted-foreground">
                  {{ conn.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-foreground truncate">{{ conn.name }}</p>
                  <p class="text-xs text-muted-foreground truncate font-mono">{{ conn.driver }} · {{ conn.host }}:{{ conn.port }} · {{ conn.database }}</p>
                </div>
              </div>
              <span class="shrink-0 text-xs font-medium" :class="dbStatusColor(conn.status)">{{ conn.status }}</span>
            </div>
          </div>
        </div>

        <!-- Alerts + System Info -->
        <div class="flex flex-col gap-3">
          <div class="rounded-lg border border-border bg-card p-4">
            <div class="flex items-center justify-between">
              <p class="text-xs font-medium text-foreground">Recent Alerts</p>
              <span v-if="alerts.length > 0" class="rounded bg-amber-500/10 px-2 py-0.5 text-xs text-amber-500">{{ alerts.length }}</span>
            </div>
            <div v-if="alerts.length === 0" class="mt-4 text-center text-sm text-muted-foreground">
              No alerts. All connections healthy.
            </div>
            <div v-else class="mt-3 space-y-2">
              <div v-for="(alert, i) in alerts" :key="i" class="flex items-start gap-3">
                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="alert.type === 'error' ? 'bg-red-500' : alert.type === 'warning' ? 'bg-amber-500' : 'bg-cyan-500'" />
                <div>
                  <p class="text-sm text-foreground">{{ alert.message }}</p>
                  <p class="text-xs text-muted-foreground">{{ alert.time }} · {{ alert.connection }}</p>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-border bg-card p-4">
            <p class="text-xs font-medium text-foreground">System Info</p>
            <div class="mt-3 space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Total Queries (24h)</span>
                <span class="font-medium text-foreground">{{ stats.total_queries_24h ?? 0 }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Avg Health Score</span>
                <span class="font-medium text-foreground">{{ stats.avg_health_score ?? 0 }} ({{ stats.avg_health_grade ?? 'N/A' }})</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Last Updated</span>
                <span class="font-mono text-xs text-foreground">{{ lastUpdated }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
