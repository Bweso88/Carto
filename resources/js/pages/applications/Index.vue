<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    Download,
    FileSpreadsheet,
    Plus,
    Search,
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
import applications from '@/routes/applications';
import type { Application, EnumOptions, Option, Paginated } from '@/types/cmdb';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: applications.index() }],
    },
});

type Filters = {
    search?: string | null;
    type?: string | null;
    lifecycle?: string | null;
    criticality?: string | null;
    domain?: string | null;
    team?: string | null;
    sort?: string | null;
};

const props = defineProps<{
    applications: Paginated<Application>;
    filters: Filters;
    options: EnumOptions & { businessDomains: Option[]; teams: Option[] };
}>();

const canEdit = computed(() => usePage().props.auth.can.editCmdb);
const { filters, reset, active } = useListFilters(applications.index().url, {
    search: null,
    type: null,
    lifecycle: null,
    criticality: null,
    domain: null,
    team: null,
    sort: null,
    ...props.filters,
} as Filters);

const criticalityOptions = computed(() => [
    ...props.options.criticalities,
    { value: 'none', label: 'Non évaluée' },
]);
const exportUrl = computed(
    () =>
        applications.export({
            query: Object.fromEntries(
                Object.entries(filters).filter(([, v]) => v),
            ),
        }).url,
);
</script>

<template>
    <Head title="Applications" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            eyebrow="Catalogue CMDB"
            title="Applications & services"
            description="Applications métier, services techniques partagés et leurs dépendances."
        >
            <template #actions>
                <Button variant="outline" size="sm" as="a" :href="exportUrl"
                    ><Download /> Exporter CSV</Button
                >
                <Button v-if="canEdit" variant="outline" size="sm" as-child>
                    <Link :href="applications.import()"
                        ><FileSpreadsheet /> Importer</Link
                    >
                </Button>
                <Button v-if="canEdit" size="sm" as-child>
                    <Link :href="applications.create()"
                        ><Plus /> Nouvelle application</Link
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
                    placeholder="Nom, code APP-… ou description…"
                    class="pl-8"
                    aria-label="Rechercher"
                />
            </div>
            <NativeSelect
                v-model="filters.type"
                :options="options.applicationTypes"
                placeholder="Tous les types"
                class="w-44"
                aria-label="Type"
            />
            <NativeSelect
                v-model="filters.lifecycle"
                :options="options.lifecycleStatuses"
                placeholder="Tout cycle de vie"
                class="w-44"
                aria-label="Cycle de vie"
            />
            <NativeSelect
                v-model="filters.criticality"
                :options="criticalityOptions"
                placeholder="Toute criticité"
                class="w-40"
                aria-label="Criticité"
            />
            <NativeSelect
                v-model="filters.domain"
                :options="options.businessDomains"
                placeholder="Tous les domaines"
                class="w-44"
                aria-label="Domaine"
            />
            <NativeSelect
                v-model="filters.team"
                :options="options.teams"
                placeholder="Toutes les équipes"
                class="w-44"
                aria-label="Équipe"
            />
            <Button v-if="active()" variant="ghost" size="sm" @click="reset"
                ><X /> Réinitialiser</Button
            >
        </div>

        <div class="overflow-hidden rounded-md border bg-card">
            <div class="overflow-x-auto">
                <table
                    v-if="props.applications.data.length"
                    class="w-full text-sm"
                >
                    <thead
                        class="border-b bg-muted/50 font-mono text-[11px] tracking-wider text-muted-foreground"
                    >
                        <tr class="text-left">
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Application"
                                    column="name"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                            <th class="px-4 py-2.5 font-medium">
                                <SortHeader
                                    label="Type"
                                    column="type"
                                    :sort="filters.sort"
                                    @sort="filters.sort = $event"
                                />
                            </th>
                            <th class="px-4 py-2.5 font-medium uppercase">
                                Domaines
                            </th>
                            <th class="px-4 py-2.5 font-medium uppercase">
                                Resp. technique
                            </th>
                            <th
                                class="px-4 py-2.5 text-right font-medium uppercase"
                                title="Serveurs · dépendances · dépendants"
                            >
                                Srv · Dép.
                            </th>
                            <th class="px-4 py-2.5 font-medium uppercase">
                                Cycle de vie
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
                            v-for="a in props.applications.data"
                            :key="a.id"
                            class="group hover:bg-muted/40"
                        >
                            <td class="max-w-sm px-4 py-2.5">
                                <Link
                                    :href="applications.show(a.id)"
                                    class="font-medium group-hover:text-primary"
                                    >{{ a.name }}</Link
                                >
                                <div
                                    class="flex items-center gap-2 font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ a.code }}
                                    <ToneBadge
                                        v-if="a.handles_personal_data"
                                        tone="accent"
                                        class="h-4 px-1 text-[10px]"
                                        >RGPD</ToneBadge
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-2.5">
                                <EnumBadge :value="a.type" />
                            </td>
                            <td class="px-4 py-2.5 text-sm">
                                <div
                                    v-if="a.business_domains?.length"
                                    class="flex max-w-56 flex-wrap gap-1"
                                >
                                    <ToneBadge
                                        v-for="d in a.business_domains"
                                        :key="d.id"
                                        tone="neutral"
                                        >{{ d.name }}</ToneBadge
                                    >
                                </div>
                                <span v-else class="text-muted-foreground/60"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2.5 text-sm">
                                <span v-if="a.technical_owner">{{
                                    a.technical_owner.name
                                }}</span>
                                <span
                                    v-else
                                    class="text-amber-600 dark:text-amber-300"
                                    >Non défini</span
                                >
                            </td>
                            <td
                                class="px-4 py-2.5 text-right font-mono text-xs whitespace-nowrap tabular-nums"
                            >
                                <span
                                    :class="
                                        a.servers_count
                                            ? ''
                                            : 'text-amber-600 dark:text-amber-300'
                                    "
                                    >{{ a.servers_count }}</span
                                >
                                <span class="text-muted-foreground">
                                    · {{ a.dependencies_count }}↗
                                    {{ a.dependents_count }}↙</span
                                >
                            </td>
                            <td class="px-4 py-2.5">
                                <EnumBadge :value="a.lifecycle_status" dot />
                            </td>
                            <td class="px-4 py-2.5">
                                <EnumBadge
                                    :value="a.criticality"
                                    empty="Non évaluée"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <EmptyState
                    v-else
                    :icon="AppWindow"
                    title="Aucune application"
                    :description="
                        active()
                            ? 'Aucune application ne correspond à ces filtres.'
                            : 'Déclarez une première application ou lancez l\'import Kerberos.'
                    "
                />
            </div>
            <Pagination :page="props.applications" noun="applications" />
        </div>
    </div>
</template>
