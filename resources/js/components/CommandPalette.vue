<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
  Database,
  GalleryVerticalEnd,
  LayoutDashboard,
  LineChart,
  MessageSquareText,
  Monitor,
  Search,
  Settings,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import { useUIStore } from '@/stores/ui';

interface CommandItem {
  title: string
  href: string
  icon: object
}

const ui = useUIStore();
const query = ref('');

const items: CommandItem[] = [
  { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { title: 'Connections', href: '/connections', icon: Database },
  { title: 'Database Graph', href: '/graph', icon: GalleryVerticalEnd },
  { title: 'AI Insights', href: '/insights', icon: MessageSquareText },
  { title: 'Query Analyzer', href: '/queries', icon: Search },
  { title: 'Monitoring', href: '/monitoring', icon: Monitor },
  { title: 'Settings', href: '/settings', icon: Settings },
];

const filteredItems = computed(() => {
  if (!query.value) return items;
  const q = query.value.toLowerCase();
  return items.filter((item) => item.title.toLowerCase().includes(q));
});

function onSelect(item: CommandItem) {
  ui.setCommandPalette(false);
  router.visit(item.href);
}

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    ui.setCommandPalette(!ui.commandPaletteOpen);
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <CommandDialog :open="ui.commandPaletteOpen" @update:open="ui.setCommandPalette">
    <CommandInput
      v-model="query"
      placeholder="Search pages..."
    />
    <CommandList>
      <CommandEmpty>No results found.</CommandEmpty>
      <CommandGroup heading="Pages">
        <CommandItem
          v-for="item in filteredItems"
          :key="item.href"
          :value="item.title"
          @select="onSelect(item)"
        >
          <component :is="item.icon" class="mr-2 h-4 w-4" />
          <span>{{ item.title }}</span>
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </CommandDialog>
</template>
