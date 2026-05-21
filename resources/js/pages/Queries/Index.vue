<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'
import ChatMessage from '@/components/ai/ChatMessage.vue'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Spinner } from '@/components/ui/spinner'
import { getKey } from '@/modules/ai/apiKeys'
import type { ProviderId } from '@/modules/ai/config'
import { MODELS, PROVIDERS } from '@/modules/ai/config'
import { useConnectionStore } from '@/stores/connection'

const store = useConnectionStore()
const sql = ref('SELECT * FROM users\nLIMIT 10;')
const running = ref(false)
const result = ref<{ cols: string[]; rows: string[][] } | null>(null)

// Chat
const messages = ref<{ role: 'user' | 'assistant'; content: string; timestamp: string }[]>([])
const input = ref('')
const thinking = ref(false)
const showHistory = ref(false)
const chatHistory = ref<{ id: number; user_message: string; ai_response: string; timestamp: string }[]>([])

// Model settings
const defaultModelId = ref(localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash')
const currentProvider = ref(getProviderFromModel(defaultModelId.value))

function getProviderFromModel(modelId: string): string {
  return MODELS.find(m => m.id === modelId)?.provider ?? 'openai'
}

function getModelsForProvider(provider: string) {
  return MODELS.filter(m => m.provider === provider)
}

function changeModel(id: string) {
  defaultModelId.value = id
  currentProvider.value = getProviderFromModel(id)
  localStorage.setItem('aetherdb_default_model', id)
}

onMounted(async () => {
  if (!store.activeConnection) {
    return
  }

  await loadChatHistory()
})

async function loadChatHistory() {
  if (!store.activeConnection) {
    return
  }

  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat-history`)
    const json = await res.json()
    chatHistory.value = json.data ?? []
  } catch {
    // silent
  }
}

function runQuery() {
  running.value = true
  result.value = null
  setTimeout(() => {
    result.value = {
      cols: ['id', 'name', 'email', 'created_at'],
      rows: [
        ['1', 'John Doe', 'john@example.com', '2024-01-15'],
        ['2', 'Jane Smith', 'jane@example.com', '2024-01-16'],
      ],
    }
    running.value = false
  }, 500)
}

function extractSQL(text: string): string {
  const match = text.match(/```(?:sql|mysql)?\s*([\s\S]*?)```/)

  return match ? match[1].trim() : text
}

function applySQL(sqlText: string) {
  sql.value = extractSQL(sqlText)
}

async function sendChat() {
  if (!input.value.trim() || !store.activeConnection || thinking.value) {
    return
  }

  const msg = input.value.trim()
  input.value = ''

  messages.value.push({ role: 'user', content: msg, timestamp: new Date().toISOString() })
  thinking.value = true
  const idx = messages.value.length
  messages.value.push({ role: 'assistant', content: '', timestamp: new Date().toISOString() })

  try {
    const modelId = localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash'
    const provider = getProviderFromModel(modelId)
    const apiKey = getKey(provider as ProviderId) ?? ''
    const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''
    const body = JSON.stringify({ message: msg, api_key: apiKey, provider, system_prompt: systemPrompt, stream: true, connection_name: store.activeConnection.name })

    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' }, body,
    })

    if (res.headers.get('Content-Type')?.includes('text/event-stream')) {
      const reader = res.body?.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      if (reader) {
        while (true) {
          const { done, value } = await reader.read()

          if (done) {
break
}

          buffer += decoder.decode(value, { stream: true })
          const lines = buffer.split('\n')
          buffer = lines.pop() ?? ''

          for (const line of lines) {
            if (!line.startsWith('data: ')) {
continue
}

            const chunk = line.slice(6)

            if (chunk === '[DONE]') {
continue
}

            try {
              const parsed = JSON.parse(chunk)
              const content = parsed?.choices?.[0]?.delta?.content ?? ''

              if (content) {
messages.value[idx] = { ...messages.value[idx], content: messages.value[idx].content + content }
}
            } catch { /* skip */ }
          }
        }
      }
    } else {
      const json = await res.json()

      if (json.data) {
messages.value[idx] = json.data
} else {
messages.value[idx] = { role: 'assistant', content: json.message ?? 'No response', timestamp: new Date().toISOString() }
}
    }

    await loadChatHistory()
  } catch {
    messages.value[idx] = { role: 'assistant', content: 'Failed', timestamp: new Date().toISOString() }
  } finally {
 thinking.value = false 
}
}

function hasSQL(text: string): boolean {
  return /```(?:sql|mysql)/.test(text)
}
</script>

<template>
  <Head title="Query Analyzer" />

  <div class="flex h-full flex-1 font-mono">
    <div class="flex flex-1 flex-col gap-4 overflow-x-auto p-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-medium text-foreground">Query Analyzer</h2>
      </div>

      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-xs text-muted-foreground">SQL Query</span>
          <div class="flex items-center gap-2">
            <Button size="sm" variant="outline" @click="sql = ''">Clear</Button>
            <Button size="sm" :disabled="running" @click="runQuery">
              <Spinner v-if="running" /> Run
            </Button>
          </div>
        </div>
        <textarea v-model="sql" class="h-28 resize-none border border-border bg-card p-3 text-xs text-foreground outline-none font-mono" placeholder="Enter SQL query..." spellcheck="false" />
      </div>

      <div v-if="running" class="flex items-center justify-center py-8 text-xs text-muted-foreground">Executing...</div>

      <div v-else-if="result" class="border border-border bg-card">
        <div class="border-b border-border bg-muted/30 px-3 py-1.5 text-xs font-medium text-foreground">Results — {{ result.rows.length }} rows</div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-border bg-muted/20">
                <th v-for="col in result.cols" :key="col" class="whitespace-nowrap px-3 py-2 text-left font-medium text-foreground">{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-border last:border-0 hover:bg-accent/20">
                <td v-for="(cell, j) in row" :key="j" class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ cell }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="w-[380px] border-l border-border bg-card flex flex-col">
      <div class="border-b border-border px-4 py-2 flex items-center justify-between">
        <div>
          <h3 class="text-xs font-medium text-foreground">AI Assistant</h3>
          <p v-if="store.activeConnection" class="text-[10px] text-muted-foreground">{{ store.activeConnection.name }}</p>
          <p v-else class="text-[10px] text-muted-foreground">Connect a database first</p>
        </div>
        <div class="flex items-center gap-1">
          <select
            class="border border-border bg-card px-1.5 py-1 text-[10px] text-foreground outline-none w-24"
            :value="defaultModelId"
            @change="(e) => changeModel((e.target as HTMLSelectElement).value)"
          >
            <optgroup v-for="p in PROVIDERS" :key="p.id" :label="p.label">
              <option v-for="m in getModelsForProvider(p.id)" :key="m.id" :value="m.id">{{ m.hint }}</option>
            </optgroup>
          </select>
          <button class="text-[10px] text-muted-foreground hover:text-foreground" @click="showHistory = true" :title="`${chatHistory.length} history`">📋</button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-border">
        <div v-if="messages.length === 0 && !thinking" class="flex h-full items-center justify-center px-4 text-center text-[10px] text-muted-foreground">
          Ask the AI to help write or optimize SQL queries
        </div>

        <div v-for="(msg, i) in messages" :key="i" class="group">
          <ChatMessage :role="msg.role" :content="msg.content" :timestamp="msg.timestamp" />
          <div v-if="msg.role === 'assistant' && hasSQL(msg.content)" class="flex gap-1 px-4 pb-2">
            <button class="text-[10px] text-blue-500 hover:text-blue-400" @click="applySQL(msg.content)">Apply SQL</button>
          </div>
        </div>

        <div v-if="thinking" class="flex items-center gap-2 px-4 py-3 text-xs text-muted-foreground">
          <Spinner /> Thinking...
        </div>
      </div>

      <div class="border-t border-border p-3">
        <div class="flex gap-2">
          <input v-model="input" type="text" placeholder="Ask about SQL, optimization..." class="flex-1 border border-border bg-card px-3 py-2 text-xs text-foreground outline-none font-mono" :disabled="!store.activeConnection || thinking" @keydown.enter="sendChat" />
          <Button size="sm" :disabled="!input.trim() || thinking || !store.activeConnection" @click="sendChat">Send</Button>
        </div>
      </div>
    </div>
  </div>

  <Dialog :open="showHistory" @update:open="showHistory = false">
    <DialogContent class="max-w-lg font-mono">
      <DialogHeader>
        <DialogTitle class="font-mono">Chat History</DialogTitle>
      </DialogHeader>
      <div class="flex flex-col gap-2 max-h-96 overflow-y-auto">
        <div v-for="h in chatHistory" :key="h.id" class="border border-border bg-card p-3 cursor-pointer hover:bg-accent/30" @click="messages.push({ role: 'user', content: h.user_message, timestamp: '' }, { role: 'assistant', content: h.ai_response, timestamp: '' }); showHistory = false">
          <p class="text-xs font-medium text-foreground truncate">{{ h.user_message }}</p>
          <p class="text-[10px] text-muted-foreground mt-1">{{ h.timestamp }}</p>
        </div>
        <div v-if="chatHistory.length === 0" class="py-8 text-center text-xs text-muted-foreground">No chat history yet</div>
      </div>
    </DialogContent>
  </Dialog>
</template>
