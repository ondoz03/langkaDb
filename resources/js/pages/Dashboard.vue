<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { Database, MessageSquareText, Monitor, Search, Share2, Plus, Wifi } from 'lucide-vue-next'
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { useAIStore } from '@/stores/ai'
import { useConnectionStore } from '@/stores/connection'

const connectionStore = useConnectionStore()
const aiStore = useAIStore()

const totalConnections = computed(() => connectionStore.connections.length)
const activeConnections = computed(() =>
  connectionStore.connections.filter(c => c.status === 'connected').length
)
const hasActive = computed(() => connectionStore.hasActiveConnection)

const stats = computed(() => [
  {
    label: 'Connections',
    value: String(totalConnections.value),
    sub: `${activeConnections.value} active`,
    icon: Database,
    href: '/connections',
  },
  {
    label: 'Active Now',
    value: String(activeConnections.value),
    sub: activeConnections.value > 0 ? 'Connected' : 'No active',
    icon: Wifi,
    href: hasActive.value ? '/monitoring' : '/connections',
    accent: activeConnections.value > 0,
  },
  {
    label: 'AI Insights',
    value: String(aiStore.recommendations.length || '—'),
    sub: aiStore.recommendations.length > 0 ? `${aiStore.recommendations.length} findings` : 'Run analysis',
    icon: MessageSquareText,
    href: hasActive.value ? '/insights' : '/connections',
  },
  {
    label: 'Query Analyzer',
    value: hasActive.value ? 'Ready' : '—',
    sub: hasActive.value ? 'Connected DB' : 'No connection',
    icon: Search,
    href: hasActive.value ? '/queries' : '/connections',
  },
])

const quickActions = computed(() => [
  {
    label: 'Add Connection',
    description: 'Connect to a MySQL or MariaDB database',
    icon: Plus,
    href: '/connections',
    disabled: false,
  },
  {
    label: 'View Schema Graph',
    description: 'Visualize your database structure',
    icon: Share2,
    href: '/graph',
    disabled: !hasActive.value,
  },
  {
    label: 'Open Monitor',
    description: 'Track database performance',
    icon: Monitor,
    href: '/monitoring',
    disabled: !hasActive.value,
  },
])

const recentConnections = computed(() => connectionStore.connections.slice(0, 5))

const activities = computed(() => {
  const items: { time: string; text: string; type: 'success' | 'info' | 'warning' | 'neutral' }[] = []

  if (connectionStore.activeConnection) {
    items.push({
      time: 'Just now',
      text: `Connected to ${connectionStore.activeConnection.name}`,
      type: 'success',
    })
  }

  connectionStore.connections.forEach(c => {
    if (c.status === 'connected' && c.name !== connectionStore.activeConnection?.name) {
      items.push({
        time: c.updated_at ?? 'Earlier',
        text: `${c.name} is connected`,
        type: 'success',
      })
    }
  })

  if (aiStore.recommendations.length > 0) {
    items.push({
      time: 'Recent',
      text: `${aiStore.recommendations.length} AI insight${aiStore.recommendations.length > 1 ? 's' : ''} available`,
      type: 'info',
    })
  }

  if (items.length === 0) {
    items.push({
      time: '',
      text: 'No recent activity. Add a database connection to get started.',
      type: 'neutral',
    })
  }

  return items
})
</script>

<template>
  <Head title="Dashboard" />

  <div class="flex h-full flex-1 flex-col overflow-x-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border px-6 py-4">
      <div>
        <h1 class="text-base font-semibold text-foreground">Dashboard</h1>
        <p class="mt-0.5 text-sm text-muted-foreground">Monitor your database connections and activity</p>
      </div>
      <div v-if="connectionStore.activeConnection" class="flex items-center gap-1.5 rounded-md border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground">
        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
        {{ connectionStore.activeConnection.name }}
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!hasActive && totalConnections === 0" class="flex flex-1 items-center justify-center p-6">
      <div class="flex flex-col items-center gap-4 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-lg border border-border bg-card">
          <Database class="h-6 w-6 text-muted-foreground" />
        </div>
        <div>
          <p class="text-sm font-medium text-foreground">No connections yet</p>
          <p class="mt-1 text-sm text-muted-foreground">Add a database connection to start exploring.</p>
        </div>
        <Link href="/connections">
          <Button variant="accent">
            <Plus class="mr-1.5 h-4 w-4" />
            Add Connection
          </Button>
        </Link>
      </div>
    </div>

    <template v-else>
      <!-- Stat cards -->
      <div class="grid gap-3 p-6 pb-0 md:grid-cols-2 lg:grid-cols-4">
        <Link
          v-for="stat in stats"
          :key="stat.label"
          :href="stat.href"
          class="group rounded-lg border border-border bg-card p-4 transition-colors hover:bg-accent/50"
        >
          <div class="flex items-start justify-between">
            <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
            <component :is="stat.icon" class="h-4 w-4 text-muted-foreground" />
          </div>
          <p class="mt-2 text-2xl font-semibold text-foreground">{{ stat.value }}</p>
          <p class="mt-0.5 text-xs" :class="stat.accent ? 'text-green-500' : 'text-muted-foreground'">{{ stat.sub }}</p>
        </Link>
      </div>

      <!-- Bottom grid -->
      <div class="grid gap-3 p-6 lg:grid-cols-2">
        <!-- Quick Actions -->
        <div class="rounded-lg border border-border bg-card p-4">
          <p class="text-xs font-medium text-foreground">Quick Actions</p>
          <div class="mt-3 grid gap-2">
            <Link
              v-for="action in quickActions"
              :key="action.label"
              :href="action.href"
              :class="[
                'flex items-center gap-3 rounded-md border border-border p-3 text-sm transition-colors',
                action.disabled
                  ? 'opacity-40 pointer-events-none'
                  : 'hover:bg-accent/50',
              ]"
            >
              <component :is="action.icon" class="h-4 w-4 text-muted-foreground" />
              <div>
                <p class="text-sm font-medium text-foreground">{{ action.label }}</p>
                <p class="text-xs text-muted-foreground">{{ action.description }}</p>
              </div>
            </Link>
          </div>
        </div>

        <!-- Activity -->
        <div class="rounded-lg border border-border bg-card p-4">
          <p class="text-xs font-medium text-foreground">Recent Activity</p>
          <div class="mt-3 space-y-3">
            <div v-for="(activity, i) in activities" :key="i" class="flex items-start gap-3">
              <span
                class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full"
                :class="{
                  'bg-green-500': activity.type === 'success',
                  'bg-cyan-500': activity.type === 'info',
                  'bg-amber-500': activity.type === 'warning',
                  'bg-muted-foreground/30': activity.type === 'neutral',
                }"
              />
              <div>
                <p class="text-sm text-foreground">{{ activity.text }}</p>
                <p v-if="activity.time" class="text-xs text-muted-foreground">{{ activity.time }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Connections table -->
      <div v-if="recentConnections.length > 0" class="px-6 pb-6">
        <div class="rounded-lg border border-border bg-card">
          <div class="flex items-center justify-between border-b border-border px-4 py-3">
            <p class="text-xs font-medium text-foreground">Connections</p>
            <Link href="/connections" class="text-xs text-muted-foreground hover:text-foreground transition-colors">
              View all
            </Link>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-border bg-muted/30">
                  <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Name</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Driver</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Host</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-muted-foreground">Database</th>
                  <th class="px-4 py-2 text-right text-xs font-medium text-muted-foreground">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                <tr v-for="conn in recentConnections" :key="conn.id" class="transition-colors hover:bg-accent/30">
                  <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                      <div class="flex h-7 w-7 items-center justify-center rounded border border-border bg-muted/50 text-xs font-medium text-muted-foreground">
                        {{ conn.name.charAt(0).toUpperCase() }}
                      </div>
                      <span class="font-medium text-foreground">{{ conn.name }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-2 text-xs text-muted-foreground font-mono uppercase">{{ conn.driver }}</td>
                  <td class="px-4 py-2 text-xs text-muted-foreground font-mono">{{ conn.host }}:{{ conn.port }}</td>
                  <td class="px-4 py-2 text-xs text-muted-foreground">{{ conn.database }}</td>
                  <td class="px-4 py-2 text-right">
                    <span
                      class="inline-flex items-center gap-1.5 rounded px-2 py-0.5 text-xs font-medium"
                      :class="conn.status === 'connected'
                        ? 'bg-green-500/10 text-green-600 dark:text-green-400'
                        : 'bg-red-500/10 text-red-600 dark:text-red-400'"
                    >
                      <span class="h-1 w-1 rounded-full" :class="conn.status === 'connected' ? 'bg-green-500' : 'bg-red-500'" />
                      {{ conn.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
