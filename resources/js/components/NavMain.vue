<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const itemsWithActive = computed(() =>
    props.items.map(item => ({
        ...item,
        active: isCurrentUrl(item.href),
    }))
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Navigation</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem
                v-for="item in itemsWithActive"
                :key="item.title"
            >
                <div
                    class="relative"
                >
                    <div
                        v-if="item.active"
                        class="absolute left-0 top-1/2 h-5 w-0.5 -translate-y-1/2 rounded-full bg-accent-brand"
                    />
                    <SidebarMenuButton
                        as-child
                        :is-active="item.active"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </div>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
