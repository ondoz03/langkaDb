<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

const stats = [
  { label: 'Active Connections', value: '3', change: '+1', direction: 'up' },
  { label: 'Queries / sec', value: '142', change: '-12%', direction: 'down' },
  { label: 'Avg Query Time', value: '0.8s', change: '+0.2s', direction: 'up' },
  { label: 'Cache Hit Rate', value: '87%', change: '+3%', direction: 'up' },
  { label: 'Slow Queries (24h)', value: '7', change: '-2', direction: 'down' },
  { label: 'Table Locks', value: '0', change: '—', direction: 'neutral' },
]

const databases = [
  { name: 'ecommerce_db', size: '1.2 GB', tables: 45, connections: 2, status: 'healthy' },
  { name: 'analytics_db', size: '3.8 GB', tables: 12, connections: 1, status: 'warning' },
  { name: 'logs_db', size: '450 MB', tables: 8, connections: 0, status: 'offline' },
]

function changeColor(d: string) {
  switch (d) {
    case 'up': return 'text-green-500'
    case 'down': return 'text-red-500'
    default: return 'text-muted-foreground'
  }
}

function dbStatusColor(s: string) {
  switch (s) {
    case 'healthy': return 'bg-green-500/10 text-green-500'
    case 'warning': return 'bg-amber-500/10 text-amber-500'
    case 'offline': return 'bg-red-500/10 text-red-500'
    default: return 'bg-muted text-muted-foreground'
  }
}
</script>

<template>
  <Head title="Monitoring" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">Monitoring</h2>
      <span class="text-xs text-muted-foreground">Last updated: just now</span>
    </div>

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
      <div
        v-for="s in stats"
        :key="s.label"
        class="rounded-lg border border-border bg-card p-4"
      >
        <p class="text-xs text-muted-foreground">{{ s.label }}</p>
        <p class="mt-1 text-xl font-bold text-foreground">{{ s.value }}</p>
        <p class="mt-0.5 text-xs" :class="changeColor(s.direction)">{{ s.change }}</p>
      </div>
    </div>

    <div class="rounded-lg border border-border bg-card p-4">
      <h3 class="text-sm font-medium text-foreground">Database Status</h3>
      <div class="mt-3 divide-y divide-border text-xs">
        <div
          v-for="db in databases"
          :key="db.name"
          class="flex items-center justify-between py-2"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-brand/10 font-mono text-xs font-medium text-accent-brand">
              {{ db.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="font-medium text-foreground">{{ db.name }}</p>
              <p class="text-muted-foreground">{{ db.size }} · {{ db.tables }} tables · {{ db.connections }} connections</p>
            </div>
          </div>
          <span class="rounded px-2 py-0.5 font-medium" :class="dbStatusColor(db.status)">
            {{ db.status }}
          </span>
        </div>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
      <div class="rounded-lg border border-border bg-card p-4">
        <h3 class="text-sm font-medium text-foreground">Queries Over Time</h3>
        <div class="mt-3 flex items-end gap-1" style="height: 100px">
          <div
            v-for="(h, i) in [40, 65, 45, 80, 55, 70, 90, 60, 75, 50, 85, 95, 70, 55, 80, 60, 75, 90, 65, 50, 85, 70, 95, 80]"
            :key="i"
            class="flex-1 rounded-sm bg-accent-brand/20 hover:bg-accent-brand/40"
            :style="{ height: h + '%' }"
          />
        </div>
        <div class="mt-1 flex justify-between text-xs text-muted-foreground">
          <span>00:00</span>
          <span>12:00</span>
          <span>23:59</span>
        </div>
      </div>
      <div class="rounded-lg border border-border bg-card p-4">
        <h3 class="text-sm font-medium text-foreground">Recent Alerts</h3>
        <div class="mt-3 divide-y divide-border text-xs">
          <div class="flex items-start gap-3 py-2">
            <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
            <div>
              <p class="text-foreground">Slow query detected on <code class="text-amber-500">analytics_db</code></p>
              <p class="text-muted-foreground">Duration: 3.2s — 2 minutes ago</p>
            </div>
          </div>
          <div class="flex items-start gap-3 py-2">
            <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-green-500" />
            <div>
              <p class="text-foreground">Connection restored <code class="text-green-500">ecommerce_db</code></p>
              <p class="text-muted-foreground">After 5m downtime — 15 minutes ago</p>
            </div>
          </div>
          <div class="flex items-start gap-3 py-2">
            <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-red-500" />
            <div>
              <p class="text-foreground">Connection lost <code class="text-red-500">logs_db</code></p>
              <p class="text-muted-foreground">SSH tunnel timeout — 1 hour ago</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
