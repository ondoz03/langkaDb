<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

const categories = [
  { title: 'Missing Indexes', count: 3, severity: 'high', desc: 'Columns used in WHERE/JOIN without index' },
  { title: 'Slow Queries', count: 7, severity: 'medium', desc: 'Queries exceeding 1s execution time' },
  { title: 'Schema Issues', count: 2, severity: 'low', desc: 'Naming inconsistencies, missing constraints' },
  { title: 'Duplicate Indexes', count: 1, severity: 'medium', desc: 'Redundant overlapping indexes' },
  { title: 'Table Size', count: '1.2 GB', severity: 'info', desc: 'Total estimated database size' },
  { title: 'Connection Health', count: '2/3', severity: 'good', desc: 'Connected databases' },
]

function severityColor(s: string) {
  switch (s) {
    case 'high': return 'text-red-500'
    case 'medium': return 'text-amber-500'
    case 'low': return 'text-blue-500'
    case 'good': return 'text-green-500'
    default: return 'text-muted-foreground'
  }
}

function severityBg(s: string) {
  switch (s) {
    case 'high': return 'bg-red-500/10'
    case 'medium': return 'bg-amber-500/10'
    case 'low': return 'bg-blue-500/10'
    case 'good': return 'bg-green-500/10'
    default: return 'bg-muted'
  }
}
</script>

<template>
  <Head title="AI Insights" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4 font-mono">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium text-foreground">AI Insights</h2>
      <span class="text-xs text-muted-foreground">Last analyzed: —</span>
    </div>

    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="cat in categories"
        :key="cat.title"
        class="border border-border bg-card p-4"
      >
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium text-foreground">{{ cat.title }}</span>
          <span
            class="px-2 py-0.5 text-xs font-medium"
            :class="[severityColor(cat.severity), severityBg(cat.severity)]"
          >
            {{ cat.count }}
          </span>
        </div>
        <p class="mt-1 text-xs text-muted-foreground">{{ cat.desc }}</p>
      </div>
    </div>

    <div class="border border-border bg-card p-4">
      <h3 class="text-sm font-medium text-foreground">Recommendations</h3>
      <div class="mt-3 divide-y divide-border text-xs">
        <div class="flex items-start gap-3 py-2">
          <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-red-500" />
          <div>
            <p class="text-foreground">Add index on <code class="text-amber-500">orders.created_at</code></p>
            <p class="text-muted-foreground">Used in WHERE clause across 12 slow queries</p>
          </div>
        </div>
        <div class="flex items-start gap-3 py-2">
          <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
          <div>
            <p class="text-foreground">Review <code class="text-amber-500">users.email</code> unique constraint</p>
            <p class="text-muted-foreground">Potential duplicate entries detected</p>
          </div>
        </div>
        <div class="flex items-start gap-3 py-2">
          <span class="mt-0.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" />
          <div>
            <p class="text-foreground">Normalize <code class="text-amber-500">address</code> field in orders</p>
            <p class="text-muted-foreground">Repeated data across 1,500 rows</p>
          </div>
        </div>
      </div>
    </div>

    <div class="border border-border bg-card p-4">
      <h3 class="text-sm font-medium text-foreground">Health Score</h3>
      <div class="mt-3 flex items-center gap-4">
        <div class="flex h-20 w-20 items-center justify-center border border-border bg-muted">
          <span class="text-2xl font-bold text-foreground">78</span>
        </div>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs">
          <span class="text-muted-foreground">Structure</span>
          <span class="text-right text-foreground">85/100</span>
          <span class="text-muted-foreground">Performance</span>
          <span class="text-right text-foreground">72/100</span>
          <span class="text-muted-foreground">Index Quality</span>
          <span class="text-right text-foreground">68/100</span>
          <span class="text-muted-foreground">Security</span>
          <span class="text-right text-foreground">90/100</span>
        </div>
      </div>
    </div>
  </div>
</template>
