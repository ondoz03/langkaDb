import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUIStore = defineStore('ui', () => {
  const sidebarCollapsed = ref(false)
  const commandPaletteOpen = ref(false)
  const toasts = ref<string[]>([])

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setCommandPalette(val: boolean) {
    commandPaletteOpen.value = val
  }

  function addToast(msg: string) {
    toasts.value.push(msg)
  }

  return {
    sidebarCollapsed,
    commandPaletteOpen,
    toasts,
    toggleSidebar,
    setCommandPalette,
    addToast,
  }
})
