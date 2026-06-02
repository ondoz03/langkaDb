<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Database, MessageSquareText, Monitor, Search, Share2, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAIStore } from '@/stores/ai';
import { useConnectionStore } from '@/stores/connection';

const connectionStore = useConnectionStore()
const aiStore = useAIStore()

const totalConnections = computed(() => connectionStore.connections.length)
const activeConnections = computed(() =>
  connectionStore.connections.filter(c => c.status === 'connected').length
)
const hasActive = computed(() => connectionStore.hasActiveConnection)

const stats = computed(() => [
  {
    label: 'Total Connections',
    value: String(totalConnections.value),
    icon: Database,
    href: '/connections',
  },
  {
    label: 'Active Now',
    value: String(activeConnections.value),
    icon: Monitor,
    href: hasActive.value ? '/monitoring' : '/connections',
    accent: activeConnections.value > 0,
  },
  {
    label: 'AI Insights',
    value: String(aiStore.recommendations.length),
    icon: MessageSquareText,
    href: hasActive.value ? '/insights' : '/connections',
  },
  {
    label: 'Query Analyzer',
    value: hasActive.value ? 'Ready' : '—',
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

const recentConnections = computed(() =>
  connectionStore.connections.slice(0, 5)
)

const activities = computed(() => {
  const items: { time: string; text: string; type: 'success' | 'info' | 'warning' }[] = []

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
      type: 'info',
    })
  }

  return items
})
</script>

<template>
  <Head title="Dashboard" />

  <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-lg font-semibold text-foreground">Overview</h1>
        <p class="text-sm text-muted-foreground">Monitor your database connections and activity</p>
      </div>
    </div>

    <div v-if="!hasActive && totalConnections === 0" class="rounded-lg border border-border bg-card p-8 text-center">
      <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-accent-brand/10">
        <Database class="h-6 w-6 text-accent-brand" />
      </div>
      <h2 class="text-base font-medium text-foreground">No connections yet</h2>
      <p class="mt-1 text-sm text-muted-foreground">Add a database connection to start exploring your schema.</p>
      <Link href="/connections">
        <Button variant="accent" class="mt-4">
          <Plus class="mr-1 h-4 w-4" />
          Add Connection
        </Button>
      </Link>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Link
        v-for="stat in stats"
        :key="stat.label"
        :href="stat.href"
        class="group rounded-lg border border-border bg-card p-5 hover:border-accent-brand/30 transition-colors"
      >
        <div class="flex items-start justify-between">
          <p class="text-xs text-muted-foreground">{{ stat.label }}</p>
          <div
            class="flex h-8 w-8 items-center justify-center rounded-lg"
            :class="stat.accent ? 'bg-accent-brand/10' : 'bg-muted'"
          >
            <component
              :is="stat.icon"
              class="h-4 w-4"
              :class="stat.accent ? 'text-accent-brand' : 'text-muted-foreground'"
            />
          </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-foreground">{{ stat.value }}</p>
      </Link>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <div class="rounded-lg border border-border bg-card p-5">
        <h3 class="text-sm font-medium text-foreground">Quick Actions</h3>
        <div class="mt-4 space-y-2">
          <Link
            v-for="action in quickActions"
            :key="action.label"
            :href="action.href"
            :class="[
              'flex items-center gap-3 rounded-lg border p-3 transition-colors',
              action.disabled
                ? 'border-border/50 opacity-50 pointer-events-none'
                : 'border-border hover:border-accent-brand/30 hover:bg-accent/50'
            ]"
          >
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent-brand/10">
              <component :is="action.icon" class="h-4 w-4 text-accent-brand" />
            </div>
            <div class="flex-1">
              <p class="text-sm font-medium text-foreground">{{ action.label }}</p>
              <p class="text-xs text-muted-foreground">{{ action.description }}</p>
            </div>
          </Link>
        </div>
      </div>

      <div class="rounded-lg border border-border bg-card p-5">
        <h3 class="text-sm font-medium text-foreground">Recent Activity</h3>
        <div class="mt-4 space-y-3">
          <div
            v-for="(activity, i) in activities"
            :key="i"
            class="flex items-start gap-3"
          >
            <span
              class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
              :class="{
                'bg-green-500': activity.type === 'success',
                'bg-accent-brand': activity.type === 'info',
                'bg-amber-500': activity.type === 'warning',
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

    <!-- Connection Table -->
    <div
      v-if="recentConnections.length > 0"
      class="rounded-lg border border-border bg-card"
    >
      <div class="flex items-center justify-between px-5 py-4 border-b border-border">
        <h3 class="text-sm font-medium text-foreground">Connections</h3>
        <Link href="/connections" class="text-xs text-accent-brand hover:underline">
          View all
        </Link>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30">
              <th class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">Name</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">Driver</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">Host</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-muted-foreground">Database</th>
              <th class="px-4 py-2.5 text-right text-xs font-medium text-muted-foreground">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr
              v-for="conn in recentConnections"
              :key="conn.id"
              class="hover:bg-accent/40 transition-colors"
            >
              <td class="px-4 py-2.5">
                <div class="flex items-center gap-2.5">
                  <div class="flex h-7 w-7 items-center justify-center rounded-md bg-accent-brand/10 font-mono text-xs font-medium text-accent-brand">
                    {{ conn.name.charAt(0).toUpperCase() }}
                  </div>
                  <span class="font-medium text-foreground">{{ conn.name }}</span>
                </div>
              </td>
              <td class="px-4 py-2.5 text-muted-foreground">{{ conn.driver }}</td>
              <td class="px-4 py-2.5 text-muted-foreground font-mono text-xs">{{ conn.host }}:{{ conn.port }}</td>
              <td class="px-4 py-2.5 text-muted-foreground">{{ conn.database }}</td>
              <td class="px-4 py-2.5 text-right">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="conn.status === 'connected'
                    ? 'bg-green-500/10 text-green-600 dark:text-green-400'
                    : 'bg-red-500/10 text-red-600 dark:text-red-400'"
                >
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
