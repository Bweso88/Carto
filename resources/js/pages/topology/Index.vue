<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Network, X } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import EmptyState from '@/components/cmdb/EmptyState.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import Legend from '@/components/topology/Legend.vue';
import NodeInspector from '@/components/topology/NodeInspector.vue';
import TopologyCanvas from '@/components/topology/TopologyCanvas.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { topology } from '@/routes';
import type { Graph, GraphNode, Option } from '@/types/cmdb';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Topologie & flux', href: topology() }] },
});

type Filters = {
    environment: string | null;
    domain: string | null;
    focus: string | null;
    depth: string;
    servers: boolean;
};

const props = defineProps<{
    graph: Graph;
    filters: Filters;
    options: {
        environments: Option[];
        businessDomains: Option[];
        applications: Option[];
    };
}>();

const filters = reactive<Filters>({ ...props.filters });
const selected = ref<GraphNode | null>(null);
const locate = ref<string | null>(null);
const canvas = ref<InstanceType<typeof TopologyCanvas> | null>(null);

watch(filters, () => {
    selected.value = null;
    router.get(
        topology().url,
        {
            environment: filters.environment ?? undefined,
            domain: filters.domain ?? undefined,
            focus: filters.focus ?? undefined,
            depth: filters.focus ? filters.depth : undefined,
            servers: filters.servers ? undefined : '0',
        },
        { preserveState: true, replace: true },
    );
});

const depthOptions: Option[] = [1, 2, 3, 4, 5].map((d) => ({
    value: String(d),
    label: `${d} niveau${d > 1 ? 'x' : ''}`,
}));
const nodeOptions = computed<Option[]>(() =>
    [...props.graph.nodes]
        .sort((a, b) => a.data.label.localeCompare(b.data.label))
        .map((n) => ({
            value: n.id,
            label: `${n.data.label} · ${n.type === 'server' ? 'serveur' : n.data.code}`,
        })),
);
const counts = computed(() => ({
    apps: props.graph.nodes.filter((n) => n.type === 'application').length,
    servers: props.graph.nodes.filter((n) => n.type === 'server').length,
    flows: props.graph.edges.filter((e) => e.kind === 'flow').length,
    insecure: props.graph.edges.filter(
        (e) => e.kind === 'flow' && e.data.encrypted === false,
    ).length,
}));

function goTo(id: string | null | undefined) {
    if (!id) return;
    canvas.value?.focusNode(id);
    selected.value = props.graph.nodes.find((n) => n.id === id) ?? null;
}
watch(locate, (id) => goTo(id));
</script>

<template>
    <Head title="Topologie & flux" />

    <div class="flex min-h-0 flex-1 flex-col">
        <div
            class="flex flex-wrap items-center gap-2 border-b px-4 py-3 md:px-6"
        >
            <div class="mr-2">
                <p
                    class="font-mono text-[10px] tracking-wider text-primary uppercase"
                >
                    Graphe des dépendances
                </p>
                <h1 class="text-lg leading-tight font-semibold">
                    Topologie & flux
                </h1>
            </div>
            <NativeSelect
                v-model="filters.environment"
                :options="options.environments"
                placeholder="Tous les env."
                class="w-36"
                aria-label="Environnement"
            />
            <NativeSelect
                v-model="filters.domain"
                :options="options.businessDomains"
                placeholder="Tous les domaines"
                class="w-44"
                aria-label="Domaine métier"
            />
            <NativeSelect
                v-model="filters.focus"
                :options="options.applications"
                placeholder="Centrer sur une application…"
                class="w-64"
                aria-label="Centrer sur"
            />
            <NativeSelect
                v-if="filters.focus"
                v-model="filters.depth"
                :options="depthOptions"
                :allow-empty="false"
                class="w-32"
                aria-label="Profondeur"
            />
            <div class="flex items-center gap-2 px-1">
                <Checkbox id="with-servers" v-model="filters.servers" />
                <Label for="with-servers" class="text-sm font-normal"
                    >Serveurs</Label
                >
            </div>
            <Button
                v-if="filters.focus || filters.domain"
                variant="ghost"
                size="sm"
                @click="Object.assign(filters, { focus: null, domain: null })"
                ><X /> Vue complète</Button
            >
            <div class="ml-auto flex items-center gap-2">
                <NativeSelect
                    v-model="locate"
                    :options="nodeOptions"
                    placeholder="Localiser un nœud…"
                    class="w-56"
                    aria-label="Localiser"
                />
            </div>
        </div>

        <div class="relative flex min-h-[560px] flex-1">
            <div class="relative min-w-0 flex-1">
                <EmptyState
                    v-if="graph.truncated"
                    :icon="Network"
                    title="Graphe trop grand"
                    description="Plus de 400 éléments : filtrez par domaine métier ou centrez sur une application."
                    class="h-full"
                />
                <EmptyState
                    v-else-if="!graph.nodes.length"
                    :icon="Network"
                    title="Rien à afficher"
                    description="Aucune application ne correspond à ces filtres."
                    class="h-full"
                />
                <TopologyCanvas
                    v-else
                    ref="canvas"
                    :nodes="graph.nodes"
                    :edges="graph.edges"
                    class="absolute inset-0"
                    @select="selected = $event"
                />

                <div
                    class="pointer-events-none absolute top-3 left-3 flex flex-wrap gap-1.5"
                >
                    <ToneBadge tone="info"
                        >{{ counts.apps }} applications</ToneBadge
                    >
                    <ToneBadge tone="neutral"
                        >{{ counts.servers }} serveurs</ToneBadge
                    >
                    <ToneBadge tone="info">{{ counts.flows }} flux</ToneBadge>
                    <ToneBadge v-if="counts.insecure" tone="danger"
                        >{{ counts.insecure }} en clair</ToneBadge
                    >
                </div>
            </div>
            <NodeInspector
                v-if="selected"
                :node="selected"
                :nodes="graph.nodes"
                :edges="graph.edges"
                class="absolute inset-y-0 right-0 z-10 md:static"
                @close="selected = null"
                @locate="goTo"
            />
        </div>

        <div class="border-t px-4 py-2 md:px-6"><Legend /></div>
    </div>
</template>
