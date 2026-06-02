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
  // Cmd/Ctrl + Shift + A
  if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key.toLowerCase() === 'a') {
    e.preventDefault()
    toggle()
  }
  // Escape closes
  if (e.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div>
    <!-- Floating Toggle Button -->
    <button
      class="fixed bottom-6 right-6 z-50 flex h-12 w-12 items-center justify-center rounded-full bg-accent-brand text-white shadow-lg hover:bg-accent-brand/90 transition-all hover:scale-105 focus:outline-none focus:ring-2 focus:ring-accent-brand focus:ring-offset-2"
      :class="open ? 'scale-0 opacity-0' : 'scale-100 opacity-100'"
      @click="toggle"
      aria-label="Open AI Chat"
      title="AI Chat (Ctrl/Cmd + Shift + A)"
    >
      <MessageSquareText class="h-5 w-5" />
    </button>

    <!-- Slide-out Sheet -->
    <Sheet v-model:open="open">
      <SheetContent
        side="right"
        class="w-[420px] max-w-full border-l border-border bg-background p-0 sm:w-[480px]"
      >
        <SheetHeader class="sr-only">
          <SheetTitle>AI Chat Assistant</SheetTitle>
          <SheetDescription>Chat with AetherDB AI about your database</SheetDescription>
        </SheetHeader>

        <!-- Chat Panel -->
        <AIChatPanel />
      </SheetContent>
    </Sheet>
  </div>
</template>
