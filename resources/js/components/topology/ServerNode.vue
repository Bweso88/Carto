<script setup lang="ts">
import { Handle, Position } from '@vue-flow/core';
import { Cloud, Container, HardDrive, Server, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { ServerNodeData } from '@/types/cmdb';
import { criticalityDot, impactClasses } from './impactClasses';

const props = defineProps<{ data: ServerNodeData; selected?: boolean }>();

const icon = computed(
    () =>
        ({ physical: HardDrive, cloud: Cloud, container: Container })[
            props.data.kind
        ] ?? Server,
);
const simulating = computed(() => props.data.impact !== undefined);
const statusDot = computed(
    () =>
        ({
            active: 'bg-emerald-500',
            maintenance: 'bg-amber-500',
            planned: 'bg-sky-500',
        })[props.data.status] ?? 'bg-slate-400',
);
</script>

<template>
    <div
        class="w-[200px] rounded border bg-muted px-3 py-2 shadow-xs transition-[opacity,box-shadow]"
        :class="[
            impactClasses(data.impact, simulating),
            selected ? 'border-primary ring-2 ring-primary/25' : 'border-input',
        ]"
    >
        <Handle type="target" :position="Position.Left" class="opacity-0!" />
        <div class="flex items-center justify-between gap-2">
            <span
                class="font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                >{{ data.kindLabel }}</span
            >
            <span class="flex items-center gap-1">
                <ShieldAlert
                    v-if="data.obsolete"
                    class="size-3 text-red-500"
                    :title="`${data.os} : fin de vie`"
                />
                <span
                    v-if="data.criticality"
                    class="size-2 rounded-full"
                    :class="criticalityDot[data.criticality]"
                />
                <span
                    class="size-2 rounded-full"
                    :class="data.impact === 'down' ? 'bg-red-500' : statusDot"
                />
            </span>
        </div>
        <div class="mt-0.5 flex items-center gap-1.5">
            <component
                :is="icon"
                class="size-3.5 shrink-0 text-muted-foreground"
            />
            <span class="truncate font-mono text-[12px] font-medium">{{
                data.label
            }}</span>
        </div>
        <div
            class="mt-0.5 truncate font-mono text-[10px] text-muted-foreground"
        >
            {{ data.ip ?? data.os ?? '—' }}
        </div>
        <Handle type="source" :position="Position.Right" class="opacity-0!" />
    </div>
</template>
