<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'

interface Conn {
  id: string
  name: string
  driver: string
  host: string
  port: number
  database: string
  status: string
  created_at: string
  updated_at: string
}

interface StatsData {
  total_connections: number
  active_connections: number
  warning_connections: number
  disconnected_connections: number
  slow_queries_24h: number
  total_queries_24h: number
  avg_health_score: number
  avg_health_grade: string
  connections: Conn[]
}

interface Alert {
  type: string
  severity: string
  message: string
  connection: string
  connection_id: string
  time: string
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
  return [
    { label: 'Total Connections', value: String(stats.value.total_connections), change: `${stats.value.active_connections} active`, direction: 'up' },
    { label: 'Health Score', value: String(stats.value.avg_health_score), change: `Grade ${stats.value.avg_health_grade}`, direction: stats.value.avg_health_score >= 70 ? 'up' : 'down' },
    { label: 'Active', value: String(stats.value.active_connections), change: `${Math.round(stats.value.active_connections / Math.max(stats.value.total_connections, 1) * 100)}%`, direction: 'up' },
    { label: 'Warnings', value: String(stats.value.warning_connections), change: `${stats.value.warning_connections} connections`, direction: stats.value.warning_connections > 0 ? 'down' : 'neutral' },
    { label: 'Disconnected', value: String(stats.value.disconnected_connections), change: `${stats.value.disconnected_connections} offline`, direction: stats.value.disconnected_connections > 0 ? 'down' : 'neutral' },
    { label: 'Slow Queries (24h)', value: String(stats.value.slow_queries_24h), change: 'last 24h', direction: stats.value.slow_queries_24h > 0 ? 'down' : 'neutral' },
  ]
})

function changeColor(d: string) {
  switch (d) {
    case 'up': return 'text-green-500'
    case 'down': return 'text-red-500'
    default: return 'text-muted-foreground'
  }
}

function dbStatusColor(s: string) {
  switch (s) {
    case 'connected': return 'bg-green-500/10 text-green-500'
    case 'warning': return 'bg-amber-500/10 text-amber-500'
    case 'disconnected': return 'bg-red-500/10 text-red-500'
    default: return 'bg-muted text-muted-foreground'
  }
}

function alertTypeColor(t: string) {
  switch (t) {
    case 'error': return 'bg-red-500'
    case 'warning': return 'bg-amber-500'
    case 'info': return 'bg-blue-500'
    default: return 'bg-muted'
  }
}

onMounted(fetchData)
</script>

<template>
  <Head title="Monitoring" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">Monitoring</h2>
      <div class="flex items-center gap-2">
        <span v-if="loading" class="text-xs text-muted-foreground">Loading...</span>
        <span v-else class="text-xs text-muted-foreground">Last updated: {{ lastUpdated }}</span>
        <button @click="fetchData" class="rounded-md border border-border bg-card px-2 py-1 text-xs text-muted-foreground hover:text-foreground transition-colors">↻ Refresh</button>
      </div>
    </div>

    <div v-if="error" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive">
      {{ error }}
    </div>

    <div v-if="!loading && !error && statCards.length" class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      <div
        v-for="s in statCards"
        :key="s.label"
        class="rounded-lg border border-border bg-card p-4"
      >
        <p class="text-xs text-muted-foreground">{{ s.label }}</p>
        <p class="mt-1 text-xl font-bold text-foreground">{{ s.value }}</p>
        <p class="mt-0.5 text-xs" :class="changeColor(s.direction)">{{ s.change }}</p>
      </div>
    </div>

    <div v-if="stats?.connections" class="rounded-lg border border-border bg-card p-4">
      <h3 class="text-sm font-medium text-foreground">Connection Status</h3>
      <div class="mt-3 divide-y divide-border text-xs">
        <div
          v-for="conn in stats.connections"
          :key="conn.id"
          class="flex items-center justify-between py-2"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-brand/10 font-mono text-xs font-medium text-accent-brand">
              {{ conn.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="font-medium text-foreground">{{ conn.name }}</p>
              <p class="text-muted-foreground">{{ conn.driver }} · {{ conn.host }}:{{ conn.port }} · {{ conn.database }}</p>
            </div>
          </div>
          <span class="rounded px-2 py-0.5 font-medium" :class="dbStatusColor(conn.status)">
            {{ conn.status }}
          </span>
        </div>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
      <div class="rounded-lg border border-border bg-card p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-medium text-foreground">Recent Alerts</h3>
        </div>
        <div v-if="alerts.length === 0" class="mt-3 text-xs text-muted-foreground">
          No alerts. All connections are healthy.
        </div>
        <div v-else class="mt-3 divide-y divide-border text-xs">
          <div v-for="(alert, i) in alerts" :key="i" class="flex items-start gap-3 py-2">
            <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full" :class="alertTypeColor(alert.type)" />
            <div>
              <p class="text-foreground">{{ alert.message }}</p>
              <p class="text-muted-foreground">{{ alert.time }}</p>
            </div>
          </div>
        </div>
      </div>
      <div class="rounded-lg border border-border bg-card p-4">
        <h3 class="text-sm font-medium text-foreground">System Info</h3>
        <div class="mt-3 space-y-2 text-xs">
          <div class="flex justify-between">
            <span class="text-muted-foreground">Total Queries (24h)</span>
            <span class="text-foreground font-medium">{{ stats?.total_queries_24h ?? 0 }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">Avg Health Score</span>
            <span class="text-foreground font-medium">{{ stats?.avg_health_score ?? 0 }} ({{ stats?.avg_health_grade ?? 'N/A' }})</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
