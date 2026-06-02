<script setup lang="ts">
import { Database } from 'lucide-vue-next';
</script>

<template>
  <div
    data-tauri-drag-region
    class="fixed top-0 left-0 right-0 z-50 flex h-10 items-center justify-between border-b bg-background/80 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/60 select-none"
  >
    <div class="flex items-center gap-2">
      <Database class="h-4 w-4 text-primary" />
      <span class="text-sm font-semibold tracking-tight">AetherDB AI</span>
    </div>
    <div class="flex items-center gap-1 text-xs text-muted-foreground">
      <span class="hidden sm:inline">v0.1.0</span>
    </div>
  </div>
</template>
