<script setup lang="ts">
import {
  Database,
  GalleryVerticalEnd,
  LayoutDashboard,
  LineChart,
  MessageSquareText,
  Monitor,
  Search,
  Settings,
  Share2,
} from 'lucide-vue-next';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
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
