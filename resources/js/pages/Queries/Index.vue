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

interface ChatSession {
  id: string
  name: string
  messages: ChatMsg[]
  createdAt: string
}

const SESSIONS_KEY = 'chat_sessions'
const ACTIVE_KEY = 'chat_active_session'

function loadSessions(): ChatSession[] {
  try {
    return JSON.parse(sessionStorage.getItem(SESSIONS_KEY) ?? '[]')
  } catch {
    return []
  }
}

function saveSessions() {
  const sessions = loadSessions()
  const existing = sessions.findIndex(s => s.id === activeSession.value)

  if (existing >= 0) {
    sessions[existing].messages = messages.value.slice(-100)
  } else {
    sessions.unshift({
      id: activeSession.value,
      name: `Chat ${new Date().toLocaleTimeString()}`,
      messages: messages.value.slice(-100),
      createdAt: new Date().toISOString(),
    })
  }

  sessionStorage.setItem(SESSIONS_KEY, JSON.stringify(sessions.slice(-20)))
}

function loadSession(id: string): ChatMsg[] {
  const sessions = loadSessions()
  const s = sessions.find(s => s.id === id)

  return s?.messages ?? []
}

const activeSession = ref(sessionStorage.getItem(ACTIVE_KEY) ?? Date.now().toString())
const messages = ref<ChatMsg[]>(loadSession(activeSession.value))
const input = ref('')
const thinking = ref(false)
const showChat = ref(false)
const chatWidth = ref(400)
const resizing = ref(false)

const defaultModelId = ref(localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash')
const currentProvider = ref(getProviderFromModel(defaultModelId.value))

function newSession() {
  saveSessions()
  activeSession.value = Date.now().toString()
  sessionStorage.setItem(ACTIVE_KEY, activeSession.value)
  messages.value = []
  saveSessions()
}

async function loadInsights() {
  if (!store.activeConnection || thinking.value) {
    return
  }

  try {
    const res = await fetch('/api/ai/analyses')
    const json = await res.json()
    const latest = json.data?.[0]

    if (!latest) {
      messages.value.push({ role: 'assistant', content: 'Belum ada分析. Buka AI Insights dulu.', timestamp: new Date().toISOString() })

      return
    }

    const findings = (latest.result?.findings ?? []).map((f: any) => `- [${f.severity}] ${f.table ? f.table + ': ' : ''}${f.message}`).join('\n')
    const recommendations = (latest.result?.recommendations ?? []).map((r: any) => `- [${r.priority}] ${r.table ? r.table + ': ' : ''}${r.message}`).join('\n')

    const msg = `📋 Hasil Analisis Terakhir:\n\n⚠️ Temuan:\n${findings}\n\n🚀 Rekomendasi:\n${recommendations}\n\nSkor: ${latest.score}/100\n\nGunakan ini sebagai konteks untuk menjawab pertanyaan saya selanjutnya.`
    messages.value.push({ role: 'user', content: 'Load hasil analisis terakhir', timestamp: new Date().toISOString() })
    messages.value.push({ role: 'assistant', content: msg, timestamp: new Date().toISOString() })
    saveSessions()
  } catch {
    messages.value.push({ role: 'assistant', content: 'Gagal mengambil analisis.', timestamp: new Date().toISOString() })
  }
}

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

  const modelId = localStorage.getItem('aetherdb_default_model') ?? 'deepseek-v4-flash'
  const provider = getProviderFromModel(modelId)
  const apiKey = getKey(provider as ProviderId) ?? ''
  const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''

  // Build history from current messages (exclude the empty placeholder)
  const history = messages.value.slice(0, idx).map(m => ({ role: m.role, content: m.content }))

  try {
    const body = JSON.stringify({
      message: msg,
      history,
      api_key: apiKey,
      provider,
      system_prompt: systemPrompt,
      connection_name: store.activeConnection.name,
    })

    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat`, {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' }, body,
    })

    const json = await res.json()

    const aiContent = json.data?.content ?? json.message ?? 'No response'

    messages.value[idx] = {
      role: 'assistant',
      content: aiContent,
      timestamp: new Date().toISOString(),
      tokens: json.data?.tokens,
    } as ChatMsg

    saveSessions()
  } catch {
    messages.value[idx] = { role: 'assistant', content: 'Failed', timestamp: new Date().toISOString() }
  } finally {
    thinking.value = false
  }
}

const showHistory = ref(false)
const chatHistory = ref<{ id: number; user_message: string; ai_response: string }[]>([])

async function loadHistory() {
  if (!store.activeConnection) {
    return
  }

  try {
    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat-history`)
    const json = await res.json()
    chatHistory.value = json.data ?? []
    showHistory.value = true
  } catch { /* silent */ }
}

function openHistoryItem(h: { user_message: string; ai_response: string }) {
  messages.value.push({ role: 'user' as const, content: h.user_message, timestamp: '' })
  messages.value.push({ role: 'assistant' as const, content: h.ai_response, timestamp: '' })
  showHistory.value = false
  saveSessions()
}

async function deleteHistoryItem(id: number) {
  await fetch(`/api/ai/chat-history/${id}`, { method: 'DELETE' })
  chatHistory.value = chatHistory.value.filter(h => h.id !== id)
}

async function clearAllHistory() {
  if (!store.activeConnection) {
    return
  }

  await fetch(`/api/connections/${store.activeConnection.id}/ai/chat-history`, { method: 'DELETE' })
  chatHistory.value = []
}

function clearChat() {
  newSession()
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
        <textarea v-model="sql" class="h-28 resize-none rounded-lg border border-border bg-card p-3 text-xs text-foreground outline-none font-mono focus:border-accent-brand/50 focus:ring-1 focus:ring-accent-brand/20 transition-colors" placeholder="Enter SQL query..." spellcheck="false" />
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
      class="fixed bottom-4 right-4 z-50 flex h-10 w-10 items-center justify-center rounded-xl bg-accent-brand text-sm font-medium text-accent-brand-foreground shadow-lg hover:opacity-90"
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
        <div class="flex items-center gap-1">
          <button class="p-1 text-muted-foreground hover:text-foreground" @click="loadHistory" title="History">📋</button>
          <button class="p-1 text-muted-foreground hover:text-foreground" @click="loadInsights" :disabled="thinking" title="Load Insights">📊</button>
          <button class="p-1 text-muted-foreground hover:text-foreground" @click="clearChat" title="Clear chat">🗑</button>
        </div>
        <div class="flex items-center gap-2">
          <select class="border border-border bg-card px-1.5 py-1 text-[10px] text-foreground outline-none w-36" :value="defaultModelId" @change="(e) => changeModel((e.target as HTMLSelectElement).value)">
            <optgroup v-for="p in PROVIDERS" :key="p.id" :label="p.label">
              <option v-for="m in getModelsForProvider(p.id)" :key="m.id" :value="m.id">{{ m.label }}</option>
            </optgroup>
          </select>
          <button class="text-xs text-muted-foreground hover:text-foreground" @click="showChat = false">✕</button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-border">
        <div v-if="messages.length === 0 && !thinking" class="flex h-full items-center justify-center px-4 text-center text-[10px] text-muted-foreground">Ask the AI to help write or optimize SQL queries</div>

        <ChatMessage v-for="(msg, i) in messages" :key="i" :role="msg.role" :content="msg.content" :timestamp="msg.timestamp" :tokens="msg.tokens" />

        <div v-if="thinking" class="flex items-center gap-2 px-4 py-3 text-xs text-muted-foreground">
          <Spinner /> Thinking...
        </div>
      </div>

      <div class="border-t border-border p-2">
        <div class="flex gap-2">
          <input v-model="input" type="text" placeholder="Ask about SQL..." class="flex-1 rounded-lg border border-border bg-card px-2 py-1.5 text-xs text-foreground outline-none font-mono focus:border-accent-brand/50 focus:ring-1 focus:ring-accent-brand/20 transition-colors" :disabled="!store.activeConnection || thinking" @keydown.enter="sendChat" />
          <Button size="sm" :disabled="!input.trim() || thinking || !store.activeConnection" @click="sendChat">Send</Button>
        </div>
      </div>
    </div>
  </div>

  <!-- History Dialog -->
  <div v-if="showHistory" class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 pt-16" @click.self="showHistory = false">
    <div class="w-[500px] max-h-[60vh] border border-border bg-card shadow-xl overflow-y-auto font-mono">
      <div class="flex items-center justify-between border-b border-border px-4 py-2">
        <span class="text-xs font-medium text-foreground">Chat History ({{ chatHistory.length }})</span>
        <div class="flex items-center gap-2">
          <button v-if="chatHistory.length > 0" class="text-[10px] text-red-500 hover:text-red-400" @click="clearAllHistory">Delete All</button>
          <button class="text-xs text-muted-foreground hover:text-foreground" @click="showHistory = false">✕</button>
        </div>
      </div>
      <div class="divide-y divide-border">
        <div v-for="h in chatHistory" :key="h.id" class="flex items-center px-4 py-3 hover:bg-accent/30 group">
          <div class="flex-1 cursor-pointer min-w-0" @click="openHistoryItem(h)">
            <p class="text-xs text-foreground truncate">{{ h.user_message }}</p>
            <p class="text-[10px] text-muted-foreground mt-0.5 truncate">{{ h.ai_response?.slice(0, 100) }}...</p>
          </div>
          <button class="ml-2 text-[10px] text-red-500 hover:text-red-400 opacity-0 group-hover:opacity-100 shrink-0" @click.stop="deleteHistoryItem(h.id)">×</button>
        </div>
        <div v-if="chatHistory.length === 0" class="px-4 py-8 text-center text-xs text-muted-foreground">No history yet</div>
      </div>
    </div>
  </div>
</template>
