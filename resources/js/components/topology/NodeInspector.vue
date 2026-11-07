<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Crosshair, ExternalLink, Flame, X } from '@lucide/vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { toneOf } from '@/components/cmdb/tones';
import { Button } from '@/components/ui/button';
import applications from '@/routes/applications';
import { impact, topology } from '@/routes';
import servers from '@/routes/servers';
import type { GraphEdge, GraphNode } from '@/types/cmdb';

const props = defineProps<{
    node: GraphNode;
    edges: GraphEdge[];
    nodes: GraphNode[];
}>();
const emit = defineEmits<{ close: []; locate: [id: string] }>();

const label = (id: string) =>
    props.nodes.find((n) => n.id === id)?.data.label ?? id;
const outgoing = () => props.edges.filter((e) => e.source === props.node.id);
const incoming = () => props.edges.filter((e) => e.target === props.node.id);
const edgeText = (e: GraphEdge) =>
    e.kind === 'flow'
        ? `${e.data.protocol}${e.data.port ? `:${e.data.port}` : ''}`
        : e.kind === 'deployment'
          ? (e.data.role ?? 'déploiement')
          : 'hébergement';
</script>

<template>
    <aside
        class="flex h-full w-full flex-col overflow-hidden border-l bg-card md:w-[340px]"
    >
        <header class="flex items-start justify-between gap-2 border-b p-4">
            <div class="min-w-0">
                <p
                    class="font-mono text-[10px] tracking-wider text-primary uppercase"
                >
                    {{ node.data.kindLabel }}
                </p>
                <h2
                    class="truncate text-lg font-semibold"
                    :class="node.type === 'server' ? 'font-mono text-base' : ''"
                >
                    {{ node.data.label }}
                </h2>
                <p class="font-mono text-[11px] text-muted-foreground">
                    {{
                        node.type === 'application'
                            ? node.data.code
                            : (node.data.ip ?? '')
                    }}
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon-sm"
                aria-label="Fermer"
                @click="emit('close')"
                ><X
            /></Button>
        </header>

        <div class="flex-1 space-y-4 overflow-y-auto p-4 text-sm">
            <div class="flex flex-wrap gap-1.5">
                <ToneBadge
                    v-if="node.data.criticality"
                    :tone="toneOf(node.data.criticality)"
                    >{{
                        node.type === 'application'
                            ? node.data.criticalityLabel
                            : node.data.criticality
                    }}</ToneBadge
                >
                <ToneBadge
                    v-if="node.type === 'server' && node.data.os"
                    :tone="node.data.obsolete ? 'danger' : 'neutral'"
                    >{{ node.data.os
                    }}{{ node.data.obsolete ? ' · EOL' : '' }}</ToneBadge
                >
                <ToneBadge
                    v-if="node.type === 'application' && node.data.obsolete"
                    tone="danger"
                    >Technologie en fin de vie</ToneBadge
                >
                <ToneBadge
                    v-if="node.type === 'application' && node.data.personalData"
                    tone="accent"
                    >RGPD</ToneBadge
                >
            </div>

            <section v-if="outgoing().length">
                <h3
                    class="mb-1.5 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                >
                    {{
                        node.type === 'application'
                            ? 'Dépend de / tourne sur'
                            : 'Hébergé par'
                    }}
                </h3>
                <ul class="space-y-1">
                    <li v-for="e in outgoing()" :key="e.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded px-2 py-1 text-left hover:bg-accent"
                            @click="emit('locate', e.target)"
                        >
                            <span class="truncate">{{ label(e.target) }}</span>
                            <span
                                class="shrink-0 font-mono text-[10px]"
                                :class="
                                    e.data.encrypted === false
                                        ? 'text-red-600 dark:text-red-400'
                                        : 'text-muted-foreground'
                                "
                                >{{ edgeText(e) }}</span
                            >
                        </button>
                    </li>
                </ul>
            </section>

            <section v-if="incoming().length">
                <h3
                    class="mb-1.5 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                >
                    {{
                        node.type === 'application' ? 'Utilisée par' : 'Héberge'
                    }}
                </h3>
                <ul class="space-y-1">
                    <li v-for="e in incoming()" :key="e.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-2 rounded px-2 py-1 text-left hover:bg-accent"
                            @click="emit('locate', e.source)"
                        >
                            <span class="truncate">{{ label(e.source) }}</span>
                            <span
                                class="shrink-0 font-mono text-[10px] text-muted-foreground"
                                >{{ edgeText(e) }}</span
                            >
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <footer class="grid gap-2 border-t p-4">
            <Button size="sm" variant="destructive" as-child>
                <Link
                    :href="
                        impact({
                            query:
                                node.type === 'server'
                                    ? { 'servers[]': node.data.id }
                                    : { 'applications[]': node.data.id },
                        })
                    "
                >
                    <Flame /> Simuler la panne
                </Link>
            </Button>
            <div class="grid grid-cols-2 gap-2">
                <Button size="sm" variant="outline" as-child>
                    <Link
                        :href="
                            node.type === 'server'
                                ? servers.show(node.data.id)
                                : applications.show(node.data.id)
                        "
                        ><ExternalLink /> Fiche</Link
                    >
                </Button>
                <Button
                    v-if="node.type === 'application'"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link :href="topology({ query: { focus: node.data.id } })"
                        ><Crosshair /> Centrer ici</Link
                    >
                </Button>
            </div>
        </footer>
    </aside>
</template>
