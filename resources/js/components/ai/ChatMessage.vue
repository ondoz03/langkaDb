<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  role: 'user' | 'assistant'
  content: string
  timestamp?: string
  tokens?: { input: number; output: number; total: number }
}

const props = defineProps<Props>()
const sqlCopied = ref<Record<number, boolean>>({})

interface SqlBlock {
  index: number
  code: string
}

const sqlBlocks = computed<SqlBlock[]>(() => {
  const blocks: SqlBlock[] = []
  const regex = /```(?:sql|mysql)\s*([\s\S]*?)```/g
  let match

  while ((match = regex.exec(props.content)) !== null) {
    blocks.push({ index: blocks.length, code: match[1].trim() })
  }

  return blocks
})

const textParts = computed(() => {
  if (sqlBlocks.value.length === 0) {
    return [{ type: 'text', content: props.content }]
  }

  const parts: { type: string; content: string; index?: number }[] = []
  const regex = /```(?:sql|mysql)\s*([\s\S]*?)```/g
  let lastIndex = 0
  let match
  let blockIdx = 0

  while ((match = regex.exec(props.content)) !== null) {
    // Text before this block
    const before = props.content.slice(lastIndex, match.index)

    if (before.trim()) {
      parts.push({ type: 'text', content: before.trim() })
    }

    // SQL block
    parts.push({ type: 'sql', content: match[1].trim(), index: blockIdx })
    blockIdx++
    lastIndex = match.index + match[0].length
  }

  // Text after last block
  const after = props.content.slice(lastIndex)

  if (after.trim()) {
    parts.push({ type: 'text', content: after.trim() })
  }

  return parts
})

function copySQL(index: number, code: string) {
  try {
    navigator.clipboard.writeText(code).catch(() => {
      fallbackCopy(code)
    })
  } catch {
    fallbackCopy(code)
  }

  sqlCopied.value[index] = true

  setTimeout(() => {
    sqlCopied.value[index] = false
  }, 1500)
}

function fallbackCopy(text: string) {
  const ta = document.createElement('textarea')
  ta.value = text
  ta.style.position = 'fixed'
  ta.style.opacity = '0'
  document.body.appendChild(ta)
  ta.select()
  document.execCommand('copy')
  document.body.removeChild(ta)
}
</script>

<template>
  <div class="flex gap-3 px-4 py-3" :class="{ 'flex-row-reverse': role === 'user' }">
    <div
      class="flex h-7 w-7 shrink-0 items-center justify-center border border-border text-xs font-medium"
      :class="role === 'user' ? 'bg-primary/10 text-foreground' : 'bg-accent text-foreground'"
    >{{ role === 'user' ? 'U' : 'AI' }}</div>

    <div class="flex max-w-[85%] flex-col gap-2">
      <template v-for="(part, i) in textParts" :key="i">
        <div v-if="part.type === 'text'" class="border border-border px-3 py-2 text-xs font-mono whitespace-pre-wrap" :class="role === 'user' ? 'bg-primary/5' : 'bg-card'">
          <div v-text="part.content" />
        </div>

        <div v-else class="border border-border bg-muted/10">
          <div class="flex items-center justify-between border-b border-border bg-muted/20 px-3 py-1">
            <span class="text-[10px] font-medium text-muted-foreground">SQL</span>
            <button
              class="flex items-center gap-1 text-[10px] text-muted-foreground hover:text-blue-500"
              @click="copySQL(part.index!, part.content)"
            >
              <svg v-if="!sqlCopied[part.index!]" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
              <span v-else class="text-green-500">✓</span>
              <span>{{ sqlCopied[part.index!] ? 'Copied!' : 'Copy' }}</span>
            </button>
          </div>
          <pre class="overflow-x-auto p-3 text-xs font-mono text-foreground"><code>{{ part.content }}</code></pre>
        </div>
      </template>

      <div class="flex items-center gap-2">
        <span v-if="timestamp" class="text-[10px] text-muted-foreground/50">{{ timestamp }}</span>
        <span v-if="tokens" class="text-[10px] text-muted-foreground/50">· {{ tokens.total.toLocaleString() }} tokens</span>
      </div>
    </div>
  </div>
</template>
