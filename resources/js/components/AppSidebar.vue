<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    Flame,
    LayoutGrid,
    Library,
    Network,
    Server,
} from '@lucide/vue';
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
import { dashboard, impact, topology } from '@/routes';
import applications from '@/routes/applications';
import references from '@/routes/references';
import servers from '@/routes/servers';
import type { NavItem } from '@/types';

const page = usePage();
// Une rubrique reste active sur ses sous-pages (/servers/12, /servers/create…)
const startsWith = (prefix: string) =>
    page.url === prefix ||
    page.url.startsWith(`${prefix}/`) ||
    page.url.startsWith(`${prefix}?`);

const consoleItems = computed<NavItem[]>(() => [
    { title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Applications',
        href: applications.index(),
        icon: AppWindow,
        isActive: startsWith('/applications'),
    },
    {
        title: 'Serveurs',
        href: servers.index(),
        icon: Server,
        isActive: startsWith('/servers'),
    },
    {
        title: 'Topologie & flux',
        href: topology(),
        icon: Network,
        isActive: startsWith('/topology'),
    },
    {
        title: "Analyse d'impact",
        href: impact(),
        icon: Flame,
        isActive: startsWith('/impact'),
    },
]);

const adminItems = computed<NavItem[]>(() => [
    {
        title: 'Référentiels',
        href: references.index(),
        icon: Library,
        isActive: startsWith('/references'),
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="consoleItems" label="Console" />
            <NavMain :items="adminItems" label="Administration" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
