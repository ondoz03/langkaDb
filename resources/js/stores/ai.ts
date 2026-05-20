import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  timestamp: string
}

export const useAIStore = defineStore('ai', () => {
  const messages = ref<ChatMessage[]>([])
  const isThinking = ref(false)
  const recommendations = ref<string[]>([])

  function addMessage(msg: ChatMessage) {
    messages.value.push(msg)
  }

  function clearMessages() {
    messages.value = []
  }

  function setThinking(val: boolean) {
    isThinking.value = val
  }

  return {
    messages,
    isThinking,
    recommendations,
    addMessage,
    clearMessages,
    setThinking,
  }
})
