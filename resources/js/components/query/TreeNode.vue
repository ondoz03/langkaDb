<script setup lang="ts">
import { ref, computed } from 'vue'
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

interface TreeNodeProps {
  node: ExplainNode
  depth: number
}

const props = defineProps<TreeNodeProps>()
const isOpen = ref(props.depth < 2)

const hasChildren = computed(() => props.node.children.length > 0)

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

const style = computed(() => getAccessStyle(props.node.access_type))

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
