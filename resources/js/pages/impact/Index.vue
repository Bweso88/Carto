<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AppWindow,
    ArrowDownRight,
    Flame,
    Mail,
    Phone,
    RotateCcw,
    Server,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import EmptyState from '@/components/cmdb/EmptyState.vue';
import KpiCard from '@/components/cmdb/KpiCard.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ScoreRing from '@/components/cmdb/ScoreRing.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { toneOf } from '@/components/cmdb/tones';
import TopologyCanvas from '@/components/topology/TopologyCanvas.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { impact } from '@/routes';
import applications from '@/routes/applications';
import servers from '@/routes/servers';
import type { Graph, ImpactReport, Option } from '@/types/cmdb';

defineOptions({
    layout: { breadcrumbs: [{ title: "Analyse d'impact", href: impact() }] },
});

const props = defineProps<{
    selection: {
        servers: Option[];
        applications: Option[];
        all_environments: boolean;
    };
    report: ImpactReport | null;
    graph: Graph | null;
    options: { servers: Option[]; applications: Option[] };
}>();

// Cibles de la simulation (modifiables avant de relancer)
const targetServers = ref<Option[]>([...props.selection.servers]);
const targetApps = ref<Option[]>([...props.selection.applications]);
const allEnvironments = ref(props.selection.all_environments);
const pickServer = ref<string | null>(null);
const pickApp = ref<string | null>(null);

function add(
    list: typeof targetServers,
    options: Option[],
    value: string | null,
) {
    const option = options.find((o) => o.value === value);
    if (option && !list.value.some((o) => o.value === value))
        list.value.push(option);
}
function onPickServer(v: string | number | null | undefined) {
    add(targetServers, props.options.servers, v ? String(v) : null);
    pickServer.value = null;
}
function onPickApp(v: string | number | null | undefined) {
    add(targetApps, props.options.applications, v ? String(v) : null);
    pickApp.value = null;
}

const hasTargets = computed(
    () => targetServers.value.length + targetApps.value.length > 0,
);

function run() {
    router.get(impact().url, {
        servers: targetServers.value.map((o) => o.value),
        applications: targetApps.value.map((o) => o.value),
        all_environments: allEnvironments.value ? '1' : undefined,
    });
}
function reset() {
    router.get(impact().url);
}

// Arbre de cascade : applications regroupées par niveau de propagation
const levels = computed(() => {
    const groups: Record<number, ImpactReport['applications']> = {};
    for (const a of props.report?.applications ?? []) {
        (groups[a.depth] ??= []).push(a);
    }

    return Object.entries(groups).map(([depth, apps]) => ({
        depth: Number(depth),
        apps,
    }));
});

const levelTitle = (d: number) =>
    d === 0 ? 'Impact direct' : `Propagation · niveau ${d}`;
const fmt = (n: number) => n.toLocaleString('fr-FR');
</script>

<template>
    <Head title="Analyse d'impact" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <section class="rounded-md border bg-card p-4">
            <div class="flex flex-col gap-1">
                <p
                    class="flex items-center gap-2 font-mono text-[11px] tracking-wider text-red-600 uppercase dark:text-red-400"
                >
                    <Flame class="size-3.5" /> Simulation de panne
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Analyse d'impact &amp; cascade
                </h1>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    Choisissez les serveurs ou applications à mettre en panne.
                    MeriMap calcule la propagation le long des déploiements et
                    des flux : ce qui tombe, ce qui se dégrade, et qui
                    mobiliser. Rien n'est modifié.
                </p>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_1fr_auto]">
                <div class="space-y-2">
                    <Label class="text-xs text-muted-foreground"
                        >Serveurs en panne</Label
                    >
                    <NativeSelect
                        :model-value="pickServer"
                        :options="options.servers"
                        placeholder="Ajouter un serveur…"
                        aria-label="Ajouter un serveur"
                        @update:model-value="onPickServer"
                    />
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="o in targetServers"
                            :key="o.value"
                            type="button"
                            class="inline-flex items-center gap-1 rounded border border-red-300 bg-red-50 px-2 py-0.5 font-mono text-xs text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300"
                            @click="
                                targetServers = targetServers.filter(
                                    (t) => t.value !== o.value,
                                )
                            "
                        >
                            <Server class="size-3" />
                            {{ o.label.split(' · ')[0] }} <X class="size-3" />
                        </button>
                    </div>
                </div>
                <div class="space-y-2">
                    <Label class="text-xs text-muted-foreground"
                        >Applications en panne</Label
                    >
                    <NativeSelect
                        :model-value="pickApp"
                        :options="options.applications"
                        placeholder="Ajouter une application…"
                        aria-label="Ajouter une application"
                        @update:model-value="onPickApp"
                    />
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="o in targetApps"
                            :key="o.value"
                            type="button"
                            class="inline-flex items-center gap-1 rounded border border-red-300 bg-red-50 px-2 py-0.5 text-xs text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-300"
                            @click="
                                targetApps = targetApps.filter(
                                    (t) => t.value !== o.value,
                                )
                            "
                        >
                            <AppWindow class="size-3" /> {{ o.label }}
                            <X class="size-3" />
                        </button>
                    </div>
                </div>
                <div class="flex flex-col justify-end gap-2">
                    <div class="flex items-center gap-2">
                        <Checkbox id="all-env" v-model="allEnvironments" />
                        <Label for="all-env" class="text-xs font-normal"
                            >Inclure hors production</Label
                        >
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="report"
                            variant="outline"
                            size="sm"
                            @click="reset"
                            ><RotateCcw /> Réinitialiser</Button
                        >
                        <Button
                            size="sm"
                            variant="destructive"
                            :disabled="!hasTargets"
                            @click="run"
                            ><Flame /> Lancer la simulation</Button
                        >
                    </div>
                </div>
            </div>
        </section>

        <template v-if="report">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-md border bg-card p-4">
                    <ScoreRing :value="report.summary.score" />
                </div>
                <KpiCard
                    label="Applications indisponibles"
                    :value="report.summary.down"
                    :hint="`dont ${report.summary.critical_down} vitales · ${report.summary.degraded} dégradées`"
                    :icon="AppWindow"
                    :tone="
                        report.summary.critical_down
                            ? 'danger'
                            : report.summary.down
                              ? 'warning'
                              : 'success'
                    "
                />
                <KpiCard
                    label="Utilisateurs impactés"
                    :value="fmt(report.summary.impacted_users)"
                    hint="apps indisponibles"
                    :icon="Users"
                    :tone="
                        report.summary.impacted_users ? 'warning' : 'default'
                    "
                />
                <KpiCard
                    label="Niveaux de cascade"
                    :value="report.summary.cascade_levels"
                    :hint="`${report.failed_servers.length} serveur(s) en panne`"
                    :icon="ArrowDownRight"
                />
            </div>

            <EmptyState
                v-if="!report.applications.length"
                :icon="Flame"
                title="Aucune application touchée"
                description="Aucune application de production n'est déployée sur ces serveurs. Cochez « Inclure hors production » ou vérifiez les déploiements."
                class="rounded-md border bg-card"
            />

            <div v-else class="grid gap-4 xl:grid-cols-5">
                <Panel
                    title="Arbre de cascade"
                    description="Propagation déterministe via le graphe de dépendances"
                    class="xl:col-span-2"
                    body-class="p-0"
                >
                    <div
                        v-for="level in levels"
                        :key="level.depth"
                        class="border-b last:border-0"
                    >
                        <div
                            class="flex items-center justify-between bg-muted/50 px-4 py-1.5 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                        >
                            <span>{{ levelTitle(level.depth) }}</span>
                            <span>{{ level.apps.length }}</span>
                        </div>
                        <ul class="divide-y divide-border/60">
                            <li
                                v-for="a in level.apps"
                                :key="a.id"
                                class="flex items-start justify-between gap-3 px-4 py-2"
                                :style="{
                                    paddingLeft: `${16 + level.depth * 14}px`,
                                }"
                            >
                                <div class="min-w-0">
                                    <Link
                                        :href="applications.show(a.id)"
                                        class="text-sm font-medium hover:text-primary"
                                        >{{ a.name }}</Link
                                    >
                                    <div
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ a.code }}
                                        <template v-if="a.is_root">
                                            · épicentre</template
                                        >
                                        <template v-else-if="a.via">
                                            · via {{ a.via.name }}</template
                                        >
                                        <template v-else>
                                            · serveur en panne</template
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex shrink-0 flex-col items-end gap-1"
                                >
                                    <ToneBadge
                                        :tone="
                                            a.status === 'down'
                                                ? 'danger'
                                                : 'warning'
                                        "
                                        dot
                                        >{{
                                            a.status === 'down'
                                                ? 'Indisponible'
                                                : 'Dégradé'
                                        }}</ToneBadge
                                    >
                                    <ToneBadge
                                        v-if="a.criticality_label"
                                        :tone="toneOf(a.criticality)"
                                        >{{ a.criticality_label }}</ToneBadge
                                    >
                                </div>
                            </li>
                        </ul>
                    </div>
                </Panel>

                <Panel
                    title="Propagation"
                    description="Serveurs en panne et applications touchées"
                    class="xl:col-span-3"
                    body-class="p-0"
                >
                    <div v-if="graph" class="relative h-[520px]">
                        <TopologyCanvas
                            flow-id="impact"
                            :nodes="graph.nodes"
                            :edges="graph.edges"
                            impact-mode
                            class="absolute inset-0"
                        />
                    </div>
                </Panel>
            </div>

            <Panel
                v-if="report.failed_servers.length"
                title="Serveurs en panne"
                :description="`${report.failed_servers.length} serveur(s), VMs hébergées incluses`"
                body-class="p-3"
            >
                <div class="flex flex-wrap gap-1.5">
                    <Link
                        v-for="s in report.failed_servers"
                        :key="s.id"
                        :href="servers.show(s.id)"
                    >
                        <ToneBadge tone="danger" dot class="hover:opacity-80">{{
                            s.hostname
                        }}</ToneBadge>
                    </Link>
                </div>
            </Panel>

            <Panel
                v-if="report.teams.length"
                title="Cellule de crise · équipes à mobiliser"
                description="Responsables techniques des applications touchées"
                body-class="p-0"
            >
                <div
                    class="grid divide-y md:grid-cols-2 md:divide-y-0 xl:grid-cols-3"
                >
                    <div
                        v-for="t in report.teams"
                        :key="t.id"
                        class="space-y-2 border-b p-4 md:border-r"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold">{{ t.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{
                                        t.lead_name ??
                                        'Responsable non renseigné'
                                    }}
                                </div>
                            </div>
                            <ToneBadge :tone="t.down ? 'danger' : 'warning'"
                                >{{ t.applications.length }} app.</ToneBadge
                            >
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <ToneBadge
                                v-for="a in t.applications"
                                :key="a.code"
                                :tone="
                                    a.status === 'down' ? 'danger' : 'warning'
                                "
                                >{{ a.name }}</ToneBadge
                            >
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button
                                v-if="t.on_call_phone"
                                size="sm"
                                variant="outline"
                                as="a"
                                :href="`tel:${t.on_call_phone.replace(/\s/g, '')}`"
                                ><Phone /> {{ t.on_call_phone }}</Button
                            >
                            <Button
                                v-if="t.email"
                                size="sm"
                                variant="outline"
                                as="a"
                                :href="`mailto:${t.email}`"
                                ><Mail /> E-mail</Button
                            >
                        </div>
                    </div>
                </div>
            </Panel>
        </template>

        <EmptyState
            v-else
            :icon="Flame"
            title="Aucune simulation en cours"
            description="Ajoutez un ou plusieurs serveurs ou applications, puis lancez la simulation. Astuce : depuis la Topologie, cliquez un nœud puis « Simuler la panne »."
            class="rounded-md border bg-card"
        />
    </div>
</template>
