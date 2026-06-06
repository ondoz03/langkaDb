<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { MessageSquareText } from 'lucide-vue-next'
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/components/ui/sheet'
import AIChatPanel from '@/components/ai/AIChatPanel.vue'

const open = ref(false)

function toggle() {
  open.value = !open.value
}

function handleKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'a') {
    e.preventDefault()
    toggle()
  }
  if (e.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
  <div>
    <!-- Floating Toggle Button -->
    <button
      class="group fixed bottom-6 right-6 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-cyan-400 to-cyan-600 transition-all duration-300 hover:scale-110 focus:outline-none"
      :class="open
        ? 'scale-0 opacity-0'
        : 'scale-100 opacity-100 shadow-[0_0_20px_rgba(0,212,255,0.15)]'"
      @click="toggle"
      aria-label="Open AI Chat"
      title="AI Chat (Ctrl/Cmd + Shift + A)"
    >
      <MessageSquareText class="h-5 w-5 text-white transition-transform duration-300 group-hover:scale-110" />
      <!-- Pulse ring -->
      <span class="absolute inset-0 rounded-full ring-2 ring-cyan-400/30 animate-ping duration-[3s]" />
    </button>

    <!-- Sheet -->
    <Sheet v-model:open="open">
      <SheetContent
        side="right"
        class="w-[440px] max-w-full border-l border-border/60 bg-[#0a0a0a] p-0 sm:w-[500px]"
      >
        <SheetHeader class="sr-only">
          <SheetTitle>AI Chat Assistant</SheetTitle>
          <SheetDescription>Chat with AetherDB AI about your database</SheetDescription>
        </SheetHeader>
        <AIChatPanel />
      </SheetContent>
    </Sheet>
  </div>
</template>
