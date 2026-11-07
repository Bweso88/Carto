<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: number | string;
    hint?: string;
    icon?: LucideIcon;
    href?: InertiaLinkProps['href'];
    tone?: 'default' | 'warning' | 'danger' | 'success';
}>();

const valueClass = computed(
    () =>
        ({
            default: 'text-foreground',
            success: 'text-emerald-600 dark:text-emerald-300',
            warning: 'text-amber-600 dark:text-amber-300',
            danger: 'text-red-600 dark:text-red-400',
        })[props.tone ?? 'default'],
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="group flex flex-col gap-3 rounded-md border bg-card p-4 transition-colors"
        :class="href ? 'hover:border-primary/60' : ''"
    >
        <div class="flex items-center justify-between gap-2">
            <span
                class="font-mono text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                >{{ label }}</span
            >
            <component :is="icon" v-if="icon" class="size-4 text-primary" />
        </div>
        <div class="flex items-baseline gap-2">
            <span
                class="font-display text-3xl font-semibold tabular-nums"
                :class="valueClass"
                >{{ value }}</span
            >
            <span
                v-if="hint"
                class="truncate font-mono text-[11px] text-muted-foreground"
                >{{ hint }}</span
            >
        </div>
    </component>
</template>
