<script setup lang="ts">
import { ref, computed } from 'vue'
import { Check, Copy, User, Bot } from 'lucide-vue-next'

interface Props {
  role: 'user' | 'assistant'
  content: string
  timestamp?: string
  tokens?: { input: number; output: number; total: number }
}

const props = defineProps<Props>()
const sqlCopied = ref<Record<number, boolean>>({})

interface CodeBlock {
  index: number
  language: string
  code: string
}

const codeBlocks = computed<CodeBlock[]>(() => {
  const blocks: CodeBlock[] = []
  const regex = /```(\w*)\s*([\s\S]*?)```/g
  let match
  while ((match = regex.exec(props.content)) !== null) {
    blocks.push({
      index: blocks.length,
      language: match[1] || 'text',
      code: match[2].trim(),
    })
  }
  return blocks
})

interface TextPart {
  type: 'text'
  content: string
}

interface CodePart {
  type: 'code'
  content: string
  language: string
  index: number
}

type MessagePart = TextPart | CodePart

const textParts = computed<MessagePart[]>(() => {
  if (codeBlocks.value.length === 0) {
    return [{ type: 'text', content: props.content }]
  }

  const parts: MessagePart[] = []
  const regex = /```(\w*)\s*([\s\S]*?)```/g
  let lastIndex = 0
  let blockIdx = 0
  let match

  while ((match = regex.exec(props.content)) !== null) {
    const before = props.content.slice(lastIndex, match.index)
    if (before.trim()) {
      parts.push({ type: 'text', content: before.trim() })
    }
    parts.push({
      type: 'code',
      language: match[1] || 'text',
      content: match[2].trim(),
      index: blockIdx,
    })
    blockIdx++
    lastIndex = match.index + match[0].length
  }

  const after = props.content.slice(lastIndex)
  if (after.trim()) {
    parts.push({ type: 'text', content: after.trim() })
  }

  return parts
})

function copyCode(index: number, code: string) {
  try {
    navigator.clipboard.writeText(code).catch(() => fallbackCopy(code))
  } catch {
    fallbackCopy(code)
  }
  sqlCopied.value[index] = true
  setTimeout(() => { sqlCopied.value[index] = false }, 1500)
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

const langLabel = (lang: string) => {
  const map: Record<string, string> = {
    sql: 'SQL', php: 'PHP', js: 'JavaScript', ts: 'TypeScript',
    vue: 'Vue', bash: 'Bash', json: 'JSON', yaml: 'YAML', md: 'Markdown',
    text: 'Text', html: 'HTML', css: 'CSS',
  }
  return map[lang] || lang.toUpperCase()
}
</script>

<template>
  <div
    class="group flex gap-3 px-5 py-4 transition-colors duration-150"
    :class="role === 'user'
      ? 'bg-accent/20 flex-row-reverse'
      : 'hover:bg-accent/10'"
  >
    <!-- Avatar -->
    <div
      class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-semibold ring-1 ring-border/50"
      :class="role === 'user'
        ? 'bg-cyan-500/15 text-cyan-400 ring-cyan-500/20'
        : 'bg-accent text-foreground ring-white/5'"
    >
      <component :is="role === 'user' ? User : Bot" class="h-4 w-4" />
    </div>

    <!-- Content -->
    <div class="flex max-w-[80%] flex-col gap-2.5" :class="role === 'user' ? 'items-end' : 'items-start'">
      <template v-for="(part, i) in textParts" :key="i">
        <!-- Text block -->
        <div
          v-if="part.type === 'text'"
          class="w-fit max-w-full rounded-lg px-3.5 py-2 text-xs leading-relaxed whitespace-pre-wrap font-mono"
          :class="role === 'user'
            ? 'bg-cyan-500/10 text-foreground border border-cyan-500/15'
            : 'text-foreground/90'"
          v-text="part.content"
        />

        <!-- Code block -->
        <div v-else class="w-full overflow-hidden rounded-lg border border-border/60 bg-[#0d0d0d]">
          <div class="flex items-center justify-between border-b border-border/40 bg-[#111] px-3 py-1.5">
            <span class="text-[10px] font-medium tracking-wider text-muted-foreground/70 uppercase">
              {{ langLabel(part.language!) }}
            </span>
            <button
              class="flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] text-muted-foreground/60 transition-colors hover:text-cyan-400 hover:bg-cyan-500/10"
              @click="copyCode(part.index!, part.content)"
            >
              <Copy v-if="!sqlCopied[part.index!]" class="h-3 w-3" />
              <Check v-else class="h-3 w-3 text-green-400" />
              <span>{{ sqlCopied[part.index!] ? 'Copied' : 'Copy' }}</span>
            </button>
          </div>
          <pre class="overflow-x-auto p-3 text-xs leading-relaxed font-mono text-foreground/80"><code>{{ part.content }}</code></pre>
        </div>
      </template>

      <!-- Meta: timestamp + tokens -->
      <div class="flex items-center gap-2 px-1">
        <span v-if="timestamp" class="text-[10px] text-muted-foreground/30 font-mono">{{ timestamp }}</span>
        <span v-if="tokens" class="text-[10px] text-muted-foreground/20 font-mono">· {{ tokens.total.toLocaleString() }}t</span>
      </div>
    </div>
  </div>
</template>
