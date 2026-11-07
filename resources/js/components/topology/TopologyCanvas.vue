<script setup lang="ts">
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { MarkerType, VueFlow, useVueFlow } from '@vue-flow/core';
import type { Edge, Node, NodeMouseEvent } from '@vue-flow/core';
import { MiniMap } from '@vue-flow/minimap';
import { computed, nextTick, watch } from 'vue';
import type { GraphEdge, GraphNode } from '@/types/cmdb';
import AppNode from './AppNode.vue';
import { layoutGraph } from './layout';
import ServerNode from './ServerNode.vue';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

const props = withDefaults(
    defineProps<{
        nodes: GraphNode[];
        edges: GraphEdge[];
        flowId?: string;
        impactMode?: boolean;
    }>(),
    {
        flowId: 'topology',
        impactMode: false,
    },
);
const emit = defineEmits<{ select: [node: GraphNode | null] }>();

const { fitView, onNodesInitialized } = useVueFlow(props.flowId);

const flowNodes = computed<Node[]>(() => {
    const pos = layoutGraph(props.nodes, props.edges);

    return props.nodes.map((n) => ({
        id: n.id,
        type: n.type,
        data: n.data,
        position: pos[n.id],
    }));
});

// En simulation, un lien est « actif » si ses deux extrémités sont touchées
const touched = computed(
    () => new Set(props.nodes.filter((n) => n.data.impact).map((n) => n.id)),
);

const flowEdges = computed<Edge[]>(() =>
    props.edges.map((e) => {
        const active =
            props.impactMode &&
            touched.value.has(e.source) &&
            touched.value.has(e.target);
        if (e.kind === 'flow') {
            const insecure = e.data.encrypted === false;
            const color = active
                ? '#ef4444'
                : insecure
                  ? '#dc2626'
                  : 'var(--chart-2)';

            return {
                id: e.id,
                source: e.source,
                target: e.target,
                animated: false,
                label: `${e.data.protocol ?? ''}${e.data.port ? `:${e.data.port}` : ''}${insecure ? ' · en clair' : ''}`,
                labelStyle: {
                    fontFamily: 'JetBrains Mono, monospace',
                    fontSize: '10px',
                    fill: insecure ? '#dc2626' : 'var(--muted-foreground)',
                },
                labelBgStyle: { fill: 'var(--background)' },
                labelBgPadding: [4, 2] as [number, number],
                style: {
                    stroke: color,
                    strokeWidth: active ? 2.5 : 1.5,
                    strokeDasharray:
                        e.data.critical === false ? '6 4' : undefined,
                    opacity: props.impactMode && !active ? 0.35 : 1,
                },
                markerEnd: {
                    type: MarkerType.ArrowClosed,
                    color,
                    width: 16,
                    height: 16,
                },
            };
        }

        return {
            id: e.id,
            source: e.source,
            target: e.target,
            style: {
                stroke: active ? '#ef4444' : 'var(--muted-foreground)',
                strokeWidth: 1,
                strokeDasharray: e.kind === 'hosting' ? '2 4' : undefined,
                opacity: props.impactMode && !active ? 0.3 : 0.55,
            },
        };
    }),
);

onNodesInitialized(() => fitView({ padding: 0.15, maxZoom: 1.1 }));
watch(
    () => props.nodes.length,
    async () => {
        await nextTick();
        void fitView({ padding: 0.15, maxZoom: 1.1 });
    },
);

function onNodeClick(e: NodeMouseEvent) {
    emit('select', props.nodes.find((n) => n.id === e.node.id) ?? null);
}

function focusNode(id: string) {
    void fitView({ nodes: [id], maxZoom: 1.3, duration: 400 });
}

defineExpose({ focusNode });
</script>

<template>
    <VueFlow
        :id="flowId"
        :nodes="flowNodes"
        :edges="flowEdges"
        :nodes-draggable="true"
        :nodes-connectable="false"
        :elements-selectable="true"
        :min-zoom="0.15"
        :max-zoom="2"
        class="mm-flow"
        @node-click="onNodeClick"
        @pane-click="emit('select', null)"
    >
        <template #node-application="nodeProps"
            ><AppNode v-bind="nodeProps"
        /></template>
        <template #node-server="nodeProps"
            ><ServerNode v-bind="nodeProps"
        /></template>
        <Background :gap="22" :size="1" pattern-color="var(--border)" />
        <Controls :show-interactive="false" position="bottom-left" />
        <MiniMap
            pannable
            zoomable
            position="bottom-right"
            :node-color="
                (n: Node) =>
                    n.data?.impact === 'down'
                        ? '#ef4444'
                        : n.data?.impact === 'degraded'
                          ? '#f59e0b'
                          : n.type === 'server'
                            ? 'var(--muted-foreground)'
                            : 'var(--primary)'
            "
            mask-color="color-mix(in srgb, var(--background) 70%, transparent)"
        />
    </VueFlow>
</template>

<style>
/* Habillage Vue Flow aux couleurs MeriMap (clair et sombre) */
.mm-flow .vue-flow__controls {
    box-shadow: none;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
}
.mm-flow .vue-flow__controls-button {
    background: var(--card);
    border-bottom: 1px solid var(--border);
    color: var(--foreground);
    fill: var(--foreground);
}
.mm-flow .vue-flow__controls-button:hover {
    background: var(--accent);
}
.mm-flow .vue-flow__minimap {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
}
.mm-flow .vue-flow__node {
    border-radius: 4px;
}
.mm-flow .vue-flow__edge-textbg {
    rx: 3;
}
</style>
