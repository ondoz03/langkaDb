<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import ChatMessage from '@/components/ai/ChatMessage.vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { getKey } from '@/modules/ai/apiKeys'
import { MODELS, PROVIDERS } from '@/modules/ai/config'
import type { ProviderId } from '@/modules/ai/config'
import { useConnectionStore } from '@/stores/connection'

const store = useConnectionStore()
const sql = ref('SELECT * FROM users LIMIT 10;')
const running = ref(false)
const result = ref<{ columns: string[]; rows: Record<string, unknown>[]; count: number } | null>(null)
const error = ref<string | null>(null)

// Chat
interface ChatMsg { role: 'user' | 'assistant'; content: string; timestamp: string; tokens?: { input: number; output: number; total: number } }
const messages = ref<ChatMsg[]>([])
const input = ref('')
const thinking = ref(false)
const showChat = ref(false)
const chatWidth = ref(400)
const resizing = ref(false)

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

async function runQuery() {
  if (!store.activeConnection || !sql.value.trim()) {
    return
  }

  running.value = true
  result.value = null
  error.value = null

  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/query`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ sql: sql.value }),
    })

    const json = await res.json()

    if (json.data) {
      result.value = json.data
    } else {
      error.value = json.message ?? 'Query failed'
    }
  } catch {
    error.value = 'Failed to execute query'
  } finally {
    running.value = false
  }
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
    const body = JSON.stringify({ message: msg, api_key: apiKey, provider, system_prompt: systemPrompt, connection_name: store.activeConnection.name })

    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' }, body,
    })

    const json = await res.json()

    if (json.data) {
      messages.value[idx] = json.data as ChatMsg
    } else {
      messages.value[idx] = { role: 'assistant', content: json.message ?? 'No response', timestamp: new Date().toISOString() }
    }
  } catch {
    messages.value[idx] = { role: 'assistant', content: 'Failed', timestamp: new Date().toISOString() }
  } finally {
    thinking.value = false
  }
}

function extractSQL(text: string): string {
  const match = text.match(/```(?:sql|mysql)?\s*([\s\S]*?)```/)

  return match ? match[1].trim() : text
}

function applySQL(sqlText: string) {
  sql.value = extractSQL(sqlText)
}

function hasSQL(text: string): boolean {
  return /```(?:sql|mysql)/.test(text)
}

// Resize
function startResize(e: MouseEvent) {
  resizing.value = true
  const startX = e.clientX
  const startW = chatWidth.value

  function onMove(ev: MouseEvent) {
    const w = startW + (startX - ev.clientX)
    chatWidth.value = Math.max(280, Math.min(800, w))
  }

  function onUp() {
    resizing.value = false
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}
</script>

<template>
  <Head title="Query Analyzer" />

  <div class="flex h-full flex-1 flex-col font-mono">
    <div class="flex items-center justify-between border-b border-border px-4 py-2">
      <h2 class="text-sm font-medium text-foreground">Query Analyzer</h2>
    </div>

    <div class="flex flex-1 flex-col gap-3 overflow-x-auto p-4">
      <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between">
          <span class="text-xs text-muted-foreground">SQL Query</span>
          <div class="flex items-center gap-2">
            <Button size="sm" variant="outline" @click="sql = ''">Clear</Button>
            <Button size="sm" :disabled="running || !store.activeConnection" @click="runQuery">
              <Spinner v-if="running" /> Run
            </Button>
          </div>
        </div>
        <textarea v-model="sql" class="h-28 resize-none border border-border bg-card p-3 text-xs text-foreground outline-none font-mono" placeholder="Enter SQL query..." spellcheck="false" />
      </div>

      <div v-if="running" class="flex items-center justify-center py-8 text-xs text-muted-foreground">Executing...</div>

      <div v-else-if="error" class="border border-red-500/30 bg-red-500/5 p-3 text-xs text-red-500">{{ error }}</div>

      <div v-else-if="result" class="border border-border bg-card">
        <div class="border-b border-border bg-muted/30 px-3 py-1.5 text-xs font-medium text-foreground">{{ result.count }} rows returned</div>
        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="border-b border-border bg-muted/20">
                <th v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-left font-medium text-foreground">{{ col }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in result.rows" :key="i" class="border-b border-border last:border-0 hover:bg-accent/20">
                <td v-for="col in result.columns" :key="col" class="whitespace-nowrap px-3 py-2 text-muted-foreground">{{ row[col] ?? 'NULL' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-else-if="!store.activeConnection" class="flex flex-1 items-center justify-center text-xs text-muted-foreground">Connect a database first</div>

      <div v-else class="flex flex-1 items-center justify-center text-xs text-muted-foreground">Write a query and click Run</div>
    </div>

    <!-- Floating Chat Button -->
    <button
      v-if="store.activeConnection"
      class="fixed bottom-4 right-4 z-50 flex h-10 w-10 items-center justify-center border border-border bg-card text-sm text-foreground shadow-lg hover:bg-accent"
      @click="showChat = !showChat"
    >AI</button>

    <!-- Chat Panel -->
    <div
      v-if="showChat"
      class="fixed bottom-16 right-4 z-50 flex flex-col border border-border bg-card shadow-xl"
      :style="{ width: chatWidth + 'px', height: '500px' }"
    >
      <!-- Resize handle -->
      <div
        class="absolute left-0 top-0 h-full w-1 cursor-col-resize hover:bg-primary/50"
        :class="{ 'bg-primary/50': resizing }"
        @mousedown="startResize"
      />

      <div class="flex items-center justify-between border-b border-border px-3 py-2">
        <div class="flex items-center gap-2">
          <select class="border border-border bg-card px-1.5 py-1 text-[10px] text-foreground outline-none w-24" :value="defaultModelId" @change="(e) => changeModel((e.target as HTMLSelectElement).value)">
            <optgroup v-for="p in PROVIDERS" :key="p.id" :label="p.label">
              <option v-for="m in getModelsForProvider(p.id)" :key="m.id" :value="m.id">{{ m.label }}</option>
            </optgroup>
          </select>
        </div>
        <button class="text-xs text-muted-foreground hover:text-foreground" @click="showChat = false">✕</button>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-border">
        <div v-if="messages.length === 0 && !thinking" class="flex h-full items-center justify-center px-4 text-center text-[10px] text-muted-foreground">Ask the AI to help write or optimize SQL queries</div>

        <div v-for="(msg, i) in messages" :key="i">
          <ChatMessage :role="msg.role" :content="msg.content" :timestamp="msg.timestamp" :tokens="msg.tokens" />
          <div v-if="msg.role === 'assistant' && hasSQL(msg.content)" class="flex gap-1 px-4 pb-2">
            <button class="text-[10px] text-blue-500 hover:text-blue-400" @click="applySQL(msg.content)">Apply SQL</button>
          </div>
        </div>

        <div v-if="thinking" class="flex items-center gap-2 px-4 py-3 text-xs text-muted-foreground">
          <Spinner /> Thinking...
        </div>
      </div>

      <div class="border-t border-border p-2">
        <div class="flex gap-2">
          <input v-model="input" type="text" placeholder="Ask about SQL..." class="flex-1 border border-border bg-card px-2 py-1.5 text-xs text-foreground outline-none font-mono" :disabled="!store.activeConnection || thinking" @keydown.enter="sendChat" />
          <Button size="sm" :disabled="!input.trim() || thinking || !store.activeConnection" @click="sendChat">Send</Button>
        </div>
      </div>
    </div>
  </div>
</template>
