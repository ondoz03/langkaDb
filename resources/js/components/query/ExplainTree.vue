<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import TreeNode from './TreeNode.vue'

interface ExplainNode {
  id: string
  type: string
  table: string | null
  cost: number
  rows: number
  filtered: number
  access_type: string
  key: string | null
  extra: string | null
  cost_info: Record<string, unknown>
  used_columns: string[]
  children: ExplainNode[]
  extra_fields: Record<string, unknown>
}

interface ExplainResult {
  query: string
  tree: ExplainNode[]
  cost_breakdown: {
    total_query_cost: number
    tables: Array<{
      table: string
      access_type: string
      rows_examined: number
      rows_produced: number
      filtered: number
      cost: Record<string, unknown>
      key: string | null
      using_index: boolean
    }>
    operations: Array<{
      type: string
      table: string | null
      cost: number
    }>
  }
  suggestions: string[]
}

interface Props {
  result: ExplainResult
  loading?: boolean
}

defineProps<Props>()

// --- Color scheme per access type ---
const accessColors: Record<string, { bg: string; text: string; label: string }> = {
  ALL: { bg: 'bg-red-500/10 border-red-500/30', text: 'text-red-400', label: 'Full Table Scan' },
  INDEX: { bg: 'bg-yellow-500/10 border-yellow-500/30', text: 'text-yellow-400', label: 'Index Scan' },
  index: { bg: 'bg-yellow-500/10 border-yellow-500/30', text: 'text-yellow-400', label: 'Index Scan' },
  RANGE: { bg: 'bg-amber-500/10 border-amber-500/30', text: 'text-amber-400', label: 'Range Scan' },
  range: { bg: 'bg-amber-500/10 border-amber-500/30', text: 'text-amber-400', label: 'Range Scan' },
  REF: { bg: 'bg-sky-500/10 border-sky-500/30', text: 'text-sky-400', label: 'Non-Unique Key Lookup' },
  ref: { bg: 'bg-sky-500/10 border-sky-500/30', text: 'text-sky-400', label: 'Non-Unique Key Lookup' },
  EQ_REF: { bg: 'bg-green-500/10 border-green-500/30', text: 'text-green-400', label: 'Unique Key Lookup' },
  eq_ref: { bg: 'bg-green-500/10 border-green-500/30', text: 'text-green-400', label: 'Unique Key Lookup' },
  CONST: { bg: 'bg-emerald-500/10 border-emerald-500/30', text: 'text-emerald-400', label: 'Constant Lookup' },
  const: { bg: 'bg-emerald-500/10 border-emerald-500/30', text: 'text-emerald-400', label: 'Constant Lookup' },
  SYSTEM: { bg: 'bg-emerald-500/10 border-emerald-500/30', text: 'text-emerald-400', label: 'System (1 row)' },
  system: { bg: 'bg-emerald-500/10 border-emerald-500/30', text: 'text-emerald-400', label: 'System (1 row)' },
  NULL: { bg: 'bg-zinc-500/10 border-zinc-500/30', text: 'text-zinc-400', label: 'No Table' },
  nested_loop: { bg: 'bg-violet-500/10 border-violet-500/30', text: 'text-violet-400', label: 'Nested Loop Join' },
  query_block: { bg: 'bg-transparent', text: 'text-zinc-300', label: 'Query Block' },
  unknown: { bg: 'bg-zinc-500/10 border-zinc-500/30', text: 'text-zinc-400', label: 'Unknown' },
}

function getAccessStyle(accessType: string) {
  return accessColors[accessType] ?? { bg: 'bg-zinc-500/10 border-zinc-500/30', text: 'text-zinc-400', label: accessType }
}

function formatCost(cost: number): string {
  if (cost >= 1000000) return (cost / 1000000).toFixed(2) + 'M'
  if (cost >= 1000) return (cost / 1000).toFixed(2) + 'K'
  return cost.toFixed(2)
}

function formatRows(rows: number): string {
  if (rows >= 1000000) return (rows / 1000000).toFixed(1) + 'M'
  if (rows >= 1000) return (rows / 1000).toFixed(1) + 'K'
  return rows.toLocaleString()
}

function formatFiltered(filtered: number): string {
  return filtered.toFixed(1) + '%'
}

function hasWarning(node: ExplainNode): boolean {
  return node.access_type === 'ALL' && node.rows > 10000
}

function getIcon(node: ExplainNode): string {
  switch (node.type) {
    case 'query_block': return '🔍'
    case 'nested_loop': return '🔄'
    case 'table': {
      const at = node.access_type
      if (at === 'ALL' || at === 'INDEX' || at === 'index') return '⚠️'
      if (at === 'const' || at === 'CONST' || at === 'system' || at === 'SYSTEM') return '✅'
      if (at === 'eq_ref' || at === 'EQ_REF' || at === 'ref' || at === 'REF') return '🔗'
      return '📋'
    }
    default: return '📄'
  }
}
</script>

<template>
  <div class="flex flex-col gap-4 font-mono">
    <!-- Query display -->
    <Card class="border-border">
      <div class="px-4 py-3">
        <div class="mb-1 text-xs font-medium text-muted-foreground">EXPLAIN FORMAT=JSON</div>
        <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded border border-border bg-muted/20 p-3 text-xs text-foreground"><code>{{ result.query }}</code></pre>
      </div>
    </Card>

    <div v-if="loading" class="flex items-center justify-center py-8 text-xs text-muted-foreground">
      <svg class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      Analyzing query...
    </div>

    <!-- Tree visualization -->
    <Card v-if="result.tree.length > 0" class="border-border">
      <Card class="border-0">
        <div class="flex items-center justify-between border-b border-border px-4 py-2">
          <span class="text-xs font-medium text-muted-foreground">
            Query Plan — Cost: {{ formatCost(result.cost_breakdown.total_query_cost) }}
          </span>
          <Badge variant="outline" class="text-[10px]">
            {{ result.cost_breakdown.tables.length }} table(s)
          </Badge>
        </div>
      </Card>

      <div class="p-3">
        <TreeNode
          v-for="node in result.tree"
          :key="node.id"
          :node="node"
          :depth="0"
        />
      </div>
    </Card>

    <!-- Cost breakdown -->
    <Card v-if="result.cost_breakdown.tables.length > 0" class="border-border">
      <div class="border-b border-border px-4 py-2">
        <span class="text-xs font-medium text-muted-foreground">Cost Breakdown</span>
      </div>
      <div class="divide-y divide-border">
        <div
          v-for="t in result.cost_breakdown.tables"
          :key="t.table"
          class="flex items-center justify-between px-4 py-2 text-xs"
        >
          <div class="flex items-center gap-2">
            <span class="text-foreground">{{ t.table }}</span>
            <Badge
              variant="outline"
              :class="getAccessStyle(t.access_type).bg"
              class="text-[10px]"
            >
              {{ t.access_type }}
            </Badge>
            <span v-if="t.using_index" class="text-[10px] text-green-500">idx</span>
          </div>
          <div class="flex items-center gap-3 text-muted-foreground">
            <Tooltip>
              <TooltipTrigger>
                <span>{{ formatRows(t.rows_examined) }} rows</span>
              </TooltipTrigger>
              <TooltipContent>
                Rows examined: {{ t.rows_examined.toLocaleString() }}
              </TooltipContent>
            </Tooltip>
            <span v-if="t.cost.prefix_cost" class="tabular-nums">
              {{ formatCost(Number(t.cost.prefix_cost)) }}
            </span>
          </div>
        </div>
      </div>
    </Card>

    <!-- Optimization suggestions -->
    <Card v-if="result.suggestions.length > 0" class="border-border">
      <div class="border-b border-border px-4 py-2">
        <span class="text-xs font-medium text-muted-foreground">Suggestions ({{ result.suggestions.length }})</span>
      </div>
      <div class="divide-y divide-border">
        <div
          v-for="(suggestion, i) in result.suggestions"
          :key="i"
          class="flex items-start gap-2 px-4 py-2 text-xs"
        >
          <span class="mt-0.5 shrink-0 text-amber-400">💡</span>
          <span class="text-foreground">{{ suggestion }}</span>
        </div>
      </div>
    </Card>

    <!-- Empty state -->
    <div
      v-if="!loading && result.tree.length === 0 && !result.query"
      class="flex flex-col items-center justify-center py-12 text-xs text-muted-foreground"
    >
      <span class="mb-2 text-2xl">🔬</span>
      <span>Run EXPLAIN on a query to see the analysis</span>
    </div>
  </div>
</template>

