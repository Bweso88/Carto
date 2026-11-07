<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    AppWindow,
    ArrowRight,
    Cpu,
    GitBranch,
    MapPinned,
    Server,
    ShieldAlert,
} from '@lucide/vue';
import { computed } from 'vue';
import KpiCard from '@/components/cmdb/KpiCard.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { dashboard, impact, topology } from '@/routes';
import applications from '@/routes/applications';
import servers from '@/routes/servers';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Tableau de bord', href: dashboard() }] },
});

const props = defineProps<{
    kpis: {
        applications: number;
        critical_applications: number;
        servers: number;
        physical_servers: number;
        virtual_servers: number;
        flows: number;
    };
    alerts: {
        servers_obsolete_os: number;
        servers_os_ending_soon: number;
        applications_obsolete_stack: number;
        unencrypted_flows: number;
        applications_without_owner: number;
        applications_without_servers: number;
        servers_without_criticality: number;
    };
    criticalApplications: {
        id: number;
        code: string;
        name: string;
        servers_count: number;
        dependents_count: number;
    }[];
    obsoleteServers: {
        id: number;
        hostname: string;
        os: string;
        since: string;
    }[];
}>();

// Points d'attention de gouvernance, du plus grave au moins grave
const alertRows = computed(() => [
    {
        label: 'Serveurs sur un OS en fin de vie',
        value: props.alerts.servers_obsolete_os,
        tone: 'danger' as const,
    },
    {
        label: 'Applications avec une technologie obsolète',
        value: props.alerts.applications_obsolete_stack,
        tone: 'danger' as const,
    },
    {
        label: 'Flux applicatifs non chiffrés',
        value: props.alerts.unencrypted_flows,
        tone: 'danger' as const,
    },
    {
        label: "OS en fin de support d'ici 6 mois",
        value: props.alerts.servers_os_ending_soon,
        tone: 'warning' as const,
    },
    {
        label: 'Applications sans responsable technique',
        value: props.alerts.applications_without_owner,
        tone: 'warning' as const,
    },
    {
        label: 'Applications sans serveur déclaré',
        value: props.alerts.applications_without_servers,
        tone: 'warning' as const,
    },
    {
        label: 'Serveurs sans criticité',
        value: props.alerts.servers_without_criticality,
        tone: 'neutral' as const,
    },
]);

const date = (iso: string) =>
    new Date(iso).toLocaleDateString('fr-FR', {
        month: 'short',
        year: 'numeric',
    });
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Console d'observabilité"
            title="Vue d'ensemble du SI"
            description="Inventaire des applications et serveurs, et points d'attention à traiter."
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
                label="Applications"
                :value="kpis.applications"
                :hint="`${kpis.critical_applications} vitales`"
                :icon="AppWindow"
                :href="applications.index()"
            />
            <KpiCard
                label="Serveurs actifs"
                :value="kpis.servers"
                :hint="`${kpis.physical_servers} physiques · ${kpis.virtual_servers} VMs`"
                :icon="Server"
                :href="servers.index()"
            />
            <KpiCard
                label="Flux applicatifs"
                :value="kpis.flows"
                hint="dépendances déclarées"
                :icon="GitBranch"
            />
            <KpiCard
                label="OS en fin de vie"
                :value="alerts.servers_obsolete_os"
                hint="serveurs exposés"
                :icon="ShieldAlert"
                :tone="alerts.servers_obsolete_os ? 'danger' : 'success'"
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <Panel
                title="Points d'attention"
                description="Qualité de la cartographie et risques"
                class="xl:col-span-1"
                body-class="p-0"
            >
                <template #actions
                    ><AlertTriangle class="size-4 text-amber-500"
                /></template>
                <ul class="divide-y">
                    <li
                        v-for="row in alertRows"
                        :key="row.label"
                        class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                    >
                        <span
                            :class="row.value ? '' : 'text-muted-foreground'"
                            >{{ row.label }}</span
                        >
                        <ToneBadge :tone="row.value ? row.tone : 'success'">{{
                            row.value
                        }}</ToneBadge>
                    </li>
                </ul>
            </Panel>

            <Panel
                title="Applications vitales"
                description="Tier 1, triées par nombre d'applications qui en dépendent"
                class="xl:col-span-2"
                body-class="p-0"
            >
                <template #actions>
                    <Link
                        :href="
                            applications.index({
                                query: { criticality: 'critical' },
                            })
                        "
                        class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        Tout voir <ArrowRight class="size-3" />
                    </Link>
                </template>
                <table
                    v-if="criticalApplications.length"
                    class="w-full text-sm"
                >
                    <thead
                        class="bg-muted/50 font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">
                                Application
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Serveurs
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Dépendants
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="app in criticalApplications"
                            :key="app.id"
                            class="hover:bg-muted/40"
                        >
                            <td class="px-4 py-2">
                                <Link
                                    :href="applications.show(app.id)"
                                    class="font-medium hover:text-primary"
                                    >{{ app.name }}</Link
                                >
                                <span
                                    class="ml-2 font-mono text-[11px] text-muted-foreground"
                                    >{{ app.code }}</span
                                >
                            </td>
                            <td
                                class="px-4 py-2 text-right font-mono text-xs tabular-nums"
                            >
                                {{ app.servers_count }}
                            </td>
                            <td
                                class="px-4 py-2 text-right font-mono text-xs tabular-nums"
                            >
                                <span
                                    :class="
                                        app.dependents_count > 2
                                            ? 'text-amber-600 dark:text-amber-300'
                                            : ''
                                    "
                                    >{{ app.dependents_count }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="p-4 text-sm text-muted-foreground">
                    Aucune application classée vitale pour l'instant.
                </p>
            </Panel>
        </div>

        <Panel
            v-if="obsoleteServers.length"
            title="Serveurs sur un système en fin de vie"
            description="À migrer en priorité"
            body-class="p-0"
        >
            <template #actions><Cpu class="size-4 text-red-500" /></template>
            <ul class="grid divide-y md:grid-cols-2 md:divide-y-0">
                <li
                    v-for="s in obsoleteServers"
                    :key="s.id"
                    class="flex items-center justify-between gap-3 border-b px-4 py-2.5 text-sm md:odd:border-r"
                >
                    <Link
                        :href="servers.show(s.id)"
                        class="font-mono text-xs font-medium hover:text-primary"
                        >{{ s.hostname }}</Link
                    >
                    <span class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground">{{
                            s.os
                        }}</span>
                        <ToneBadge tone="danger"
                            >fin {{ date(s.since) }}</ToneBadge
                        >
                    </span>
                </li>
            </ul>
        </Panel>

        <p
            class="flex items-center gap-2 font-mono text-[11px] text-muted-foreground"
        >
            <MapPinned class="size-3.5" /> Explorez les dépendances dans la
            <Link :href="topology()" class="text-primary hover:underline"
                >topologie</Link
            >
            ou simulez une panne dans
            <Link :href="impact()" class="text-primary hover:underline"
                >l'analyse d'impact</Link
            >
        </p>
    </div>
</template>
