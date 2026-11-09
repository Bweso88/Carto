<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Download,
    FileSpreadsheet,
    Plus,
    Search,
    ServerOff,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import EmptyState from '@/components/cmdb/EmptyState.vue';
import EnumBadge from '@/components/cmdb/EnumBadge.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Pagination from '@/components/cmdb/Pagination.vue';
import SortHeader from '@/components/cmdb/SortHeader.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useListFilters } from '@/composables/useListFilters';
import servers from '@/routes/servers';
import type { EnumOptions, Option, Paginated, Server } from '@/types/cmdb';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Serveurs', href: servers.index() }] },
});

type Filters = {
    search?: string | null;
    kind?: string | null;
    status?: string | null;
    criticality?: string | null;
    environment?: string | null;
    site?: string | null;
    domain?: string | null;
    sort?: string | null;
};

const props = defineProps<{
    servers: Paginated<Server>;
    filters: Filters;
    options: EnumOptions & {
        environments: Option[];
        sites: Option[];
        businessDomains: Option[];
    };
}>();

const canEdit = computed(() => usePage().props.auth.can.editCmdb);
const { filters, reset, active } = useListFilters(servers.index().url, {
    search: null,
    kind: null,
    status: null,
    criticality: null,
    environment: null,
    site: null,
    domain: null,
    sort: null,
    ...props.filters,
} as Filters);

const criticalityOptions = computed(() => [
    ...props.options.criticalities,
    { value: 'none', label: 'Non évaluée' },
]);
const exportUrl = computed(
    () =>
        servers.export({
            query: Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v),
            ),
        }).url,
);
</script>

<template>
    <Head title="Serveurs" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            eyebrow="Catalogue CMDB"
            title="Serveurs"
            description="Serveurs physiques, machines virtuelles et instances cloud."
        >
            <template #actions>
                <Button variant="outline" size="sm" as="a" :href="exportUrl"
                    ><Download /> Exporter CSV</Button
                >
                <Button v-if="canEdit" variant="outline" size="sm" as-child>
                    <Link :href="servers.import()"
                        ><FileSpreadsheet /> Importer</Link
                    >
                </Button>
                <Button v-if="canEdit" size="sm" as-child>
                    <Link :href="servers.create()"
                        ><Plus /> Nouveau serveur</Link
                    >
                </Button>
            </template>
        </PageHeader>

        <div
            class="flex flex-wrap items-center gap-2 rounded-md border bg-card p-3"
        >
            <div class="relative min-w-56 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filters.search"
                    placeholder="Nom d'hôte, FQDN ou adresse IP…"
                    class="pl-8"
                    aria-label="Rechercher"
                />
            </div>
            <NativeSelect
                v-model="filters.kind"
                :options="options.serverKinds"
                placeholder="Tous les types"
                class="w-40"
                aria-label="Type"
            />
            <NativeSelect
                v-model="filters.status"
                :options="options.serverStatuses"
                placeholder="Tous les statuts"
                class="w-40"
                aria-label="Statut"
            />
            <NativeSelect
                v-model="filters.criticality"
                :options="criticalityOptions"
                placeholder="Toute criticité"
                class="w-40"
                aria-label="Criticité"
            />
            <NativeSelect
                v-model="filters.environment"
                :options="options.environments"
                placeholder="Tous les env."
                class="w-36"
                aria-label="Environnement"
            />
            <NativeSelect
                v-model="filters.site"
                :options="options.sites"
                placeholder="Tous les sites"
                class="w-44"
                aria-label="Site"
            />
            <NativeSelect
                v-model="filters.domain"
                :options="options.businessDomains"
                placeholder="Tous les domaines"
                class="w-48"
                aria-label="Domaine métier servi"
            />
            <Button v-if="active()" variant="ghost" size="sm" @click="reset"
                ><X /> Réinitialiser</Button
            >
        </div>

        <div class="overflow-hidden rounded-md border bg-card">
            <div class="overflow-x-auto">
                <table v-if="props.servers.data.length" class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 font-mono text-[11px] tracking-wider text-muted-foreground"
                    >
                        <tr class="text-left">
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Serveur"
                                    column="hostname"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Type"
                                    column="kind"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                            <th class="px-4 py-2.5 font-medium uppercase">
                                Système
                            </th>
                            <th class="px-4 py-2.5 font-medium uppercase">
                                Env. / Site
                            </th>
                            <th
                                class="px-4 py-2.5 text-right font-medium uppercase"
                            >
                                Apps
                            </th>
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Statut"
                                    column="status"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Criticité"
                                    column="criticality"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="s in props.servers.data"
                            :key="s.id"
                            class="group hover:bg-muted/40"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="servers.show(s.id)"
                                    class="font-mono text-[13px] font-medium group-hover:text-primary"
                                    >{{ s.hostname }}</Link
                                >
                                <div
                                    class="font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ s.primary_ip ?? '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="text-sm">{{ s.kind?.label }}</span>
                                <span
                                    v-if="s.guests_count"
                                    class="ml-1.5 font-mono text-[11px] text-muted-foreground"
                                    >· {{ s.guests_count }} VM</span
                                >
                            </td>
                            <td class="px-4 py-2.5">
                                <template v-if="s.os">
                                    <span class="text-sm">{{
                                        s.os.label
                                    }}</span>
                                    <ToneBadge
                                        v-if="s.os.obsolete"
                                        tone="danger"
                                        class="ml-1.5"
                                        >EOL</ToneBadge
                                    >
                                </template>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2.5 text-sm">
                                <span class="font-mono text-xs">{{
                                    s.environment?.code ?? '—'
                                }}</span>
                                <div class="text-xs text-muted-foreground">
                                    {{ s.site?.name }}
                                </div>
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-mono text-xs tabular-nums"
                            >
                                {{ s.applications_count }}
                            </td>
                            <td class="px-4 py-2.5">
                                <EnumBadge :value="s.status" dot />
                            </td>
                            <td class="px-4 py-2.5">
                                <EnumBadge
                                    :value="s.criticality"
                                    empty="Non évaluée"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :icon="ServerOff"
                    title="Aucun serveur"
                    :description="
                        active()
                            ? 'Aucun serveur ne correspond à ces filtres.'
                            : 'Ajoutez un premier serveur ou lancez l\'import Kerberos.'
                    "
                />
            </div>
            <Pagination :page="props.servers" noun="serveurs" />
        </div>
    </div>
</template>
