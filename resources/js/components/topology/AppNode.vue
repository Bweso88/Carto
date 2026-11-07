<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { AppWindow, Boxes, Cloud, Database, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { AppNodeData } from '@/types/cmdb';
import { criticalityDot, impactClasses } from './impactClasses';

const props = defineProps<{ data: AppNodeData; selected?: boolean }>();

const icon = computed(
    () =>
        ({ technical: Boxes, saas: Cloud, database: Database })[
            props.data.kind
        ] ?? AppWindow,
);
const simulating = computed(() => props.data.impact !== undefined);
</script>

<template>
    <div
        class="w-[220px] rounded border bg-card px-3 py-2 text-card-foreground shadow-xs transition-[opacity,box-shadow]"
        :class="[
            impactClasses(data.impact, simulating),
            selected ? 'border-primary ring-2 ring-primary/25' : 'border-input',
            data.kind === 'technical' ? 'border-dashed' : '',
        ]"
    >
        <Handle type="target" :position="Position.Left" class="opacity-0!" />
        <div class="flex items-center justify-between gap-2">
            <span
                class="truncate font-mono text-[10px] tracking-wider text-primary uppercase"
                >{{ data.kindLabel }}</span
            >
            <span class="flex items-center gap-1">
                <ShieldAlert v-if="data.obsolete" class="size-3 text-red-500" />
                <span
                    v-if="data.criticality"
                    class="size-2 rounded-full"
                    :class="criticalityDot[data.criticality]"
                    :title="data.criticalityLabel ?? ''"
                />
            </span>
        </div>
        <div class="mt-0.5 flex items-center gap-1.5">
            <component
                :is="icon"
                class="size-3.5 shrink-0 text-muted-foreground"
            />
            <span class="truncate text-[13px] leading-tight font-semibold">{{
                data.label
            }}</span>
        </div>
        <div
            class="mt-0.5 flex items-center justify-between font-mono text-[10px] text-muted-foreground"
        >
            <span>{{ data.code }}</span>
            <span
                v-if="data.root"
                class="font-semibold text-red-600 dark:text-red-400"
                >ÉPICENTRE</span
            >
            <span
                v-else-if="data.impact === 'down'"
                class="text-red-600 dark:text-red-400"
                >indisponible</span
            >
            <span
                v-else-if="data.impact === 'degraded'"
                class="text-amber-600 dark:text-amber-300"
                >dégradé</span
            >
        </div>
        <Handle type="source" :position="Position.Right" class="opacity-0!" />
    </div>
</template>
