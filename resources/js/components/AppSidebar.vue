<script setup lang="ts">
import {
  Database,
  LayoutDashboard,
  MessageSquareText,
  Monitor,
  Search,
  Settings,
  Share2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useConnectionStore } from '@/stores/connection';
import type { NavItem } from '@/types';

const store = useConnectionStore()

const dbNavItems: NavItem[] = [
  {
    title: 'Database Graph',
    href: '/graph',
    icon: Share2,
  },
  {
    title: 'AI Insights',
    href: '/insights',
    icon: MessageSquareText,
  },
  {
    title: 'Query Analyzer',
    href: '/queries',
    icon: Search,
  },
  {
    title: 'Monitoring',
    href: '/monitoring',
    icon: Monitor,
  },
];

const mainNavItems = computed<NavItem[]>(() => [
  {
    title: 'Dashboard',
    href: '/dashboard',
    icon: LayoutDashboard,
  },
  {
    title: 'Connections',
    href: '/connections',
    icon: Database,
  },
  ...(store.hasActiveConnection ? dbNavItems : []),
]);

const secondaryNavItems: NavItem[] = [
  {
    title: 'Settings',
    href: '/settings',
    icon: Settings,
  },
];
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <a href="/dashboard">
              <AppLogo />
            </a>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavMain :items="secondaryNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
