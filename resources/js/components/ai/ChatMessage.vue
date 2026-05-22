<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  role: 'user' | 'assistant'
  content: string
  timestamp?: string
  tokens?: { input: number; output: number; total: number }
}

const props = defineProps<Props>()
const sqlCopied = ref(false)

const sqlBlocks = computed(() => {
  const blocks: string[] = []
  const regex = /```(?:sql|mysql)?\s*([\s\S]*?)```/g
  let match

  while ((match = regex.exec(props.content)) !== null) {
    blocks.push(match[1].trim())
  }

  return blocks
})

function copySQL(sql: string) {
  navigator.clipboard.writeText(sql)
  sqlCopied.value = true

  setTimeout(() => {
    sqlCopied.value = false
  }, 1500)
}
</script>

<template>
  <div class="flex gap-3 px-4 py-3" :class="{ 'flex-row-reverse': role === 'user' }">
    <div
      class="flex h-7 w-7 shrink-0 items-center justify-center border border-border text-xs font-medium"
      :class="role === 'user' ? 'bg-primary/10 text-foreground' : 'bg-accent text-foreground'"
    >{{ role === 'user' ? 'U' : 'AI' }}</div>

    <div class="flex max-w-[80%] flex-col gap-1">
      <div class="border border-border px-3 py-2 text-xs font-mono whitespace-pre-wrap" :class="role === 'user' ? 'bg-primary/5' : 'bg-card'">
        <div v-if="role === 'assistant' && sqlBlocks.length > 0" class="mb-1 flex justify-end">
          <button
            class="flex items-center gap-1 text-[10px] text-muted-foreground hover:text-blue-500"
            :title="sqlCopied ? 'Copied!' : 'Copy SQL'"
            @click="copySQL(sqlBlocks[0])"
          >
            <span v-if="sqlCopied">✓</span>
            <span v-else>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
              </svg>
            </span>
          </button>
        </div>
        <div v-text="content" />
      </div>
      <div class="flex items-center gap-2">
        <span v-if="timestamp" class="text-[10px] text-muted-foreground/50">{{ timestamp }}</span>
        <span v-if="tokens" class="text-[10px] text-muted-foreground/50">· {{ tokens.total.toLocaleString() }} tokens</span>
      </div>
    </div>
  </div>
</template>
