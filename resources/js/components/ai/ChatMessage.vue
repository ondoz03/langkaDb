<script setup lang="ts">
import { ref, computed } from 'vue'

interface Props {
  role: 'user' | 'assistant'
  content: string
  timestamp?: string
}

const props = defineProps<Props>()
const copied = ref(false)
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

function copyAll() {
  navigator.clipboard.writeText(props.content)
  copied.value = true

  setTimeout(() => {
    copied.value = false
  }, 1500)
}

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
        <div class="mb-1 flex justify-end gap-2">
          <button v-if="role === 'assistant' && sqlBlocks.length > 0" class="text-[10px] text-blue-500 hover:text-blue-400" @click="copySQL(sqlBlocks[0])">{{ sqlCopied ? 'SQL Copied!' : 'Copy SQL' }}</button>
          <button class="text-[10px] text-muted-foreground hover:text-foreground" @click="copyAll">{{ copied ? 'Copied!' : 'Copy all' }}</button>
        </div>
        <div v-text="content" />
      </div>
      <span v-if="timestamp" class="text-[10px] text-muted-foreground/50">{{ timestamp }}</span>
    </div>
  </div>
</template>
