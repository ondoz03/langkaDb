<script setup lang="ts">
import { ref, computed } from 'vue'
import { Card } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

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

<!-- Recursive tree node component -->
<script setup lang="ts">
// TreeNode sub-component for recursive rendering
interface TreeNodeProps {
  node: ExplainNode
  depth: number
}

const treeNodeProps = defineProps<TreeNodeProps>()
const isOpen = ref(treeNodeProps.depth < 2) // Auto-open first 2 levels

const hasChildren = computed(() => treeNodeProps.node.children.length > 0)
const style = computed(() => getAccessStyle(treeNodeProps.node.access_type))
</script>

<template>
  <div
    class="tree-node mb-1 rounded border text-xs transition-all"
    :class="[
      style.bg,
      hasWarning(node) ? 'border-red-500/50' : 'border-border/50',
    ]"
  >
    <!-- Node header -->
    <div
      class="flex cursor-pointer items-center gap-2 px-3 py-2 hover:bg-muted/30"
      :class="{ 'border-b border-border/30': isOpen && hasChildren }"
      @click="isOpen = !isOpen"
    >
      <!-- Expand/collapse icon -->
      <span
        v-if="hasChildren"
        class="shrink-0 text-[10px] text-muted-foreground transition-transform"
        :class="{ 'rotate-90': isOpen }"
      >
        ▶
      </span>
      <span v-else class="w-3 shrink-0" />

      <!-- Node icon -->
      <span class="shrink-0 text-xs">{{ getIcon(node) }}</span>

      <!-- Table name / type -->
      <span v-if="node.table" class="font-medium text-foreground">{{ node.table }}</span>
      <span v-else-if="node.type === 'nested_loop'" class="font-medium text-violet-300">Nested Loop</span>
      <span v-else class="font-medium text-muted-foreground">{{ style.label }}</span>

      <!-- Access type badge -->
      <Badge
        variant="outline"
        :class="[style.text, 'text-[10px]']"
      >
        {{ node.access_type }}
      </Badge>

      <!-- Key used -->
      <span v-if="node.key" class="text-[10px] text-sky-400">{{ node.key }}</span>

      <!-- Warning indicator -->
      <span v-if="hasWarning(node)" class="text-[10px] text-red-400">⚠</span>

      <div class="ml-auto flex items-center gap-3 text-[10px] text-muted-foreground">
        <!-- Row count tooltip -->
        <Tooltip v-if="node.rows > 0 || node.type === 'table'">
          <TooltipTrigger>
            <span>{{ formatRows(node.rows) }} rows</span>
          </TooltipTrigger>
          <TooltipContent>
            <div class="flex flex-col gap-1">
              <span>Rows examined: {{ node.rows.toLocaleString() }}</span>
              <span v-if="node.filtered < 100">Filtered: {{ formatFiltered(node.filtered) }}</span>
              <span>Access: {{ style.label }}</span>
            </div>
          </TooltipContent>
        </Tooltip>

        <!-- Cost -->
        <Tooltip v-if="node.cost > 0">
          <TooltipTrigger>
            <span class="tabular-nums">{{ formatCost(node.cost) }}</span>
          </TooltipTrigger>
          <TooltipContent>
            <div class="flex flex-col gap-1">
              <span>Prefix cost: {{ node.cost.toFixed(2) }}</span>
              <span v-if="node.cost_info.read_cost">Read: {{ node.cost_info.read_cost }}</span>
              <span v-if="node.cost_info.eval_cost">Eval: {{ node.cost_info.eval_cost }}</span>
            </div>
          </TooltipContent>
        </Tooltip>
      </div>
    </div>

    <!-- Children (collapsible) -->
    <div v-if="hasChildren && isOpen" class="ml-4 border-l border-border/30 pl-2 py-1">
      <TreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
      />
    </div>
  </div>
</template>

<style scoped>
.tree-node {
  transition: border-color 0.15s ease;
}

.tree-node:hover {
  border-color: hsl(var(--border));
}
</style>
