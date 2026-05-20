<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
</script>

<template>
  <Head title="Database Graph" />

  <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-medium">Database Graph</h2>
    </div>
    <div class="relative flex-1 border border-sidebar-border/70 dark:border-sidebar-border">
      <PlaceholderPattern />
    </div>
  </div>
</template>
