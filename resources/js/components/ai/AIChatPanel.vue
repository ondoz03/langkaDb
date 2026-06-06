<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue'
import ChatMessage from '@/components/ai/ChatMessage.vue'
import { Button } from '@/components/ui/button'
import { Send, Sparkles, Eraser } from 'lucide-vue-next'
import { getKey } from '@/modules/ai/apiKeys'
import { MODELS } from '@/modules/ai/config'
import { useConnectionStore } from '@/stores/connection'
import { useAsyncJob } from '@/composables/useAsyncJob'

interface ChatMsg {
  role: 'user' | 'assistant'
  content: string
  timestamp: string
  tokens?: { input: number; output: number; total: number }
}

const QUICK_PROMPTS = [
  'What are the slowest queries?',
  'Show me missing indexes',
  'Describe the database schema',
  'Any security issues?',
  'Generate table documentation',
]

const store = useConnectionStore()
const messages = ref<ChatMsg[]>([])
const input = ref('')
const thinking = ref(false)
const asyncJob = useAsyncJob()
const chatContainer = ref<HTMLElement | null>(null)
const showQuickPrompts = ref(true)
const inputFocused = ref(false)

const canChat = computed(() => store.activeConnection && !thinking.value)

watch(() => asyncJob.jobResult.value, (result) => {
  if (result?.status === 'completed' && result?.result) {
    const msg = result.result as { role?: string; content?: string; timestamp?: string }
    messages.value.push({
      role: msg.role === 'user' ? 'user' : 'assistant',
      content: msg.content ?? 'No response',
      timestamp: msg.timestamp ?? new Date().toISOString(),
    })
    thinking.value = false
    scrollToBottom()
  } else if (result?.status === 'failed') {
    messages.value.push({
      role: 'assistant',
      content: `Error: ${result.error ?? 'Job failed'}`,
      timestamp: new Date().toISOString(),
    })
    thinking.value = false
    scrollToBottom()
  }
})

function scrollToBottom() {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight
    }
  })
}

function focusInput() {
  inputFocused.value = true
  showQuickPrompts.value = false
}

async function send() {
  if (!input.value.trim() || !store.activeConnection) return

  const msg = input.value.trim()
  input.value = ''

  messages.value.push({ role: 'user', content: msg, timestamp: new Date().toISOString() })
  showQuickPrompts.value = false
  thinking.value = true
  scrollToBottom()

  const { provider, apiKey } = getActiveProvider()
  const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''
  const history = messages.value.slice(0, -1).map(m => ({ role: m.role, content: m.content }))

  // Try streaming first
  try {
    const response = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat-stream`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' },
      body: JSON.stringify({
        message: msg, history, api_key: apiKey, provider,
        system_prompt: systemPrompt, connection_name: store.activeConnection.name,
      }),
    })

    if (!response.ok) throw new Error(`HTTP ${response.status}`)

    const streamMsg: ChatMsg = {
      role: 'assistant', content: '', timestamp: new Date().toISOString(),
    }
    messages.value.push(streamMsg)
    thinking.value = false

    if (!response.body) throw new Error('Streaming not supported')
    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()
      if (done) break
      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() ?? ''

      for (const line of lines) {
        const trimmed = line.trim()
        if (!trimmed || !trimmed.startsWith('data: ')) continue
        try {
          const data = JSON.parse(trimmed.slice(6))
          if (data.type === 'chunk' && data.content) {
            streamMsg.content += data.content
            messages.value = [...messages.value]
            scrollToBottom()
          } else if (data.type === 'done') {
            streamMsg.content = data.content ?? streamMsg.content
            messages.value = [...messages.value]
          } else if (data.type === 'error') {
            messages.value.pop()
            messages.value.push({ role: 'assistant', content: `Error: ${data.message}`, timestamp: new Date().toISOString() })
            messages.value = [...messages.value]
          }
        } catch { /* skip malformed JSON */ }
      }
    }
    scrollToBottom()
    return
  } catch {
    // Streaming failed — fallback to async job
  }

  // Fallback: async job polling
  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat-async`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ message: msg, history, api_key: apiKey, provider, system_prompt: systemPrompt, connection_name: store.activeConnection.name }),
    })
    const json = await res.json()
    if (json.data?.job_id) {
      asyncJob.startPolling(json.data.job_id, 2000)
    } else {
      thinking.value = false
      messages.value.push({ role: 'assistant', content: json.message ?? 'Failed to dispatch job', timestamp: new Date().toISOString() })
    }
  } catch {
    thinking.value = false
    messages.value.push({ role: 'assistant', content: 'Failed to get response', timestamp: new Date().toISOString() })
  }
}

function pickPrompt(p: string) {
  input.value = p
  inputFocused.value = true
  send()
}

function clearChat() {
  messages.value = []
  showQuickPrompts.value = true
}

function getActiveProvider(): { provider: string; apiKey: string } {
  const modelId = localStorage.getItem('aetherdb_default_model') ?? ''
  const model = MODELS.find(m => m.id === modelId)
  const provider = model?.provider ?? 'openai'
  const apiKey = getKey(provider) ?? ''
  return { provider, apiKey }
}
</script>

<template>
  <div class="flex h-full flex-1 flex-col bg-[#0a0a0a]">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-border/60 px-4 py-3">
      <div class="flex items-center gap-2.5">
        <div class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan-500/15">
          <Sparkles class="h-3.5 w-3.5 text-cyan-400" />
        </div>
        <div>
          <h3 class="text-sm font-medium text-foreground">AI Assistant</h3>
          <p v-if="store.activeConnection" class="text-[10px] text-muted-foreground/60 font-mono leading-tight">
            {{ store.activeConnection.name }}
          </p>
          <p v-else class="text-[10px] text-muted-foreground/40 leading-tight">
            No active connection
          </p>
        </div>
      </div>
      <button
        v-if="messages.length > 0"
        class="flex items-center gap-1 rounded-md px-2 py-1 text-[10px] text-muted-foreground/50 transition-colors hover:text-foreground hover:bg-accent/50"
        @click="clearChat"
      >
        <Eraser class="h-3 w-3" />
        Clear
      </button>
    </div>

    <!-- Messages -->
    <div
      ref="chatContainer"
      class="flex-1 overflow-y-auto scroll-smooth"
    >
      <!-- Empty state -->
      <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center px-6 text-center">
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-500/10 ring-1 ring-cyan-500/20">
          <Sparkles class="h-6 w-6 text-cyan-400" />
        </div>
        <p class="mb-1 text-sm font-medium text-foreground/80">Ask about your database</p>
        <p class="mb-6 text-xs text-muted-foreground/50">
          Connected to <span class="font-mono text-foreground/60">{{ store.activeConnection?.name || '—' }}</span>
        </p>

        <!-- Quick prompts -->
        <div v-if="showQuickPrompts" class="flex w-full max-w-sm flex-col gap-2">
          <button
            v-for="p in QUICK_PROMPTS"
            :key="p"
            class="w-full rounded-lg border border-border/50 bg-card/50 px-4 py-2.5 text-left text-xs text-muted-foreground/70 transition-all hover:border-cyan-500/30 hover:bg-cyan-500/5 hover:text-foreground/90"
            :disabled="!canChat"
            @click="pickPrompt(p)"
          >
            <span class="font-mono">{{ p }}</span>
          </button>
        </div>

        <p v-if="!store.activeConnection" class="mt-4 text-xs text-amber-500/70">Connect to a database first</p>
      </div>

      <!-- Message list -->
      <div v-else class="divide-y divide-border/20">
        <ChatMessage
          v-for="(msg, i) in messages"
          :key="i"
          :role="msg.role"
          :content="msg.content"
          :timestamp="msg.timestamp"
          :tokens="msg.tokens"
        />
      </div>

      <!-- Thinking indicator -->
      <div v-if="thinking" class="flex items-center gap-2.5 px-5 py-4" aria-live="polite" aria-label="AI is thinking">
        <div class="flex items-center gap-1.5">
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-cyan-400/60 animate-delay-0" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-cyan-400/60 animate-delay-150" />
          <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-cyan-400/60 animate-delay-300" />
        </div>
        <span class="text-xs text-muted-foreground/50 font-mono">Thinking</span>
      </div>
    </div>

    <!-- Input area -->
    <div class="border-t border-border/60 bg-[#0d0d0d] p-3.5">
      <div
        class="flex items-end gap-2 rounded-lg border transition-colors"
        :class="inputFocused
          ? 'border-cyan-500/40 bg-card shadow-[0_0_12px_rgba(0,212,255,0.06)]'
          : 'border-border/60 bg-card/80'"
      >
        <textarea
          v-model="input"
          :placeholder="canChat ? 'Ask about your database...' : 'Connect to a database first'"
          class="min-h-[38px] flex-1 resize-none bg-transparent px-3.5 py-2.5 text-xs font-mono text-foreground placeholder:text-muted-foreground/30 outline-none"
          :disabled="!canChat"
          rows="1"
          @focus="focusInput"
          @keydown.enter.shift.exact="input += '\n'"
          @keydown.enter.exact="send"
        />
        <Button
          size="sm"
          class="mb-1 mr-1 h-7 w-7 rounded-md p-0"
          :disabled="!input.trim() || !canChat"
          @click="send"
        >
          <Send class="h-3.5 w-3.5" />
        </Button>
      </div>
      <p class="mt-1.5 text-[10px] text-muted-foreground/25 text-center font-mono">
        Shift+Enter for new line · Enter to send
      </p>
    </div>
  </div>
</template>
