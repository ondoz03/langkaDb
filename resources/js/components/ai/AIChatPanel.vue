<script setup lang="ts">
import { ref } from 'vue'
import ChatMessage from '@/components/ai/ChatMessage.vue'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { getKey } from '@/modules/ai/apiKeys'
import { MODELS } from '@/modules/ai/config'
import { useConnectionStore } from '@/stores/connection'

function getActiveProvider(): { provider: string; apiKey: string } {
  const modelId = localStorage.getItem('aetherdb_default_model') ?? ''
  const model = MODELS.find(m => m.id === modelId)
  const provider = model?.provider ?? 'openai'
  const apiKey = getKey(provider) ?? ''

  return { provider, apiKey }
}

interface ChatMsg {
  role: 'user' | 'assistant'
  content: string
  timestamp: string
}

const store = useConnectionStore()
const messages = ref<ChatMsg[]>([])
const input = ref('')
const thinking = ref(false)

async function send() {
  if (!input.value.trim() || !store.activeConnection) {
    return
  }

  const msg = input.value.trim()
  input.value = ''

  messages.value.push({ role: 'user', content: msg, timestamp: new Date().toISOString() })
  thinking.value = true

  try {
    const { provider, apiKey } = getActiveProvider()
    const systemPrompt = localStorage.getItem('aetherdb_system_prompt') ?? ''
    const res = await fetch(`/api/connections/${store.activeConnection.id}/ai/chat`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ message: msg, api_key: apiKey, provider, system_prompt: systemPrompt }),
    })

    const json = await res.json()

    if (json.data) {
      messages.value.push(json.data)
    } else {
      messages.value.push({
        role: 'assistant',
        content: json.message ?? 'No response',
        timestamp: new Date().toISOString(),
      })
    }
  } catch {
    messages.value.push({
      role: 'assistant',
      content: 'Failed to get response',
      timestamp: new Date().toISOString(),
    })
  } finally {
    thinking.value = false
  }
}
</script>

<template>
  <div class="flex h-full flex-1 flex-col font-mono">
    <div class="border-b border-border px-4 py-2">
      <h3 class="text-sm font-medium text-foreground">AI Chat</h3>
      <p v-if="store.activeConnection" class="text-xs text-muted-foreground">
        {{ store.activeConnection.name }}
      </p>
    </div>

    <div class="flex-1 overflow-y-auto divide-y divide-border">
      <div v-if="messages.length === 0" class="flex h-full items-center justify-center px-4 text-center text-xs text-muted-foreground">
        Ask anything about your database
      </div>

      <ChatMessage
        v-for="(msg, i) in messages"
        :key="i"
        :role="msg.role"
        :content="msg.content"
        :timestamp="msg.timestamp"
      />

      <div v-if="thinking" class="flex items-center gap-2 px-4 py-3 text-xs text-muted-foreground">
        <Spinner />
        Thinking...
      </div>
    </div>

    <div class="border-t border-border p-3">
      <div class="flex gap-2">
        <input
          v-model="input"
          type="text"
          placeholder="Ask about your database..."
          class="flex-1 border border-border bg-card px-3 py-2 text-xs text-foreground outline-none placeholder:text-muted-foreground/50"
          :disabled="!store.activeConnection || thinking"
          @keydown.enter="send"
        />
        <Button size="sm" :disabled="!input.trim() || thinking || !store.activeConnection" @click="send">
          Send
        </Button>
      </div>
    </div>
  </div>
</template>
