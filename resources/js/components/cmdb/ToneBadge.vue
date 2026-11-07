<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

export type Tone =
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'accent'
    | 'neutral';

const props = withDefaults(
    defineProps<{ tone?: Tone; dot?: boolean; class?: string }>(),
    {
        tone: 'neutral',
        dot: false,
    },
);

// Badges d'état « console » : texte mono 11 px, fond teinté, bordure fine (DESIGN.md)
const tones: Record<Tone, string> = {
    success:
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-300 dark:border-emerald-400/25',
    warning:
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-400/10 dark:text-amber-300 dark:border-amber-400/25',
    danger: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/15 dark:text-red-300 dark:border-red-400/30',
    info: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-cyan-400/10 dark:text-cyan-300 dark:border-cyan-400/25',
    accent: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-400/10 dark:text-violet-300 dark:border-violet-400/25',
    neutral:
        'bg-slate-100 text-slate-600 border-slate-200 dark:bg-white/5 dark:text-slate-300 dark:border-white/10',
};
const dots: Record<Tone, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-sky-500 dark:bg-cyan-400',
    accent: 'bg-violet-500',
    neutral: 'bg-slate-400',
};

const classes = computed(() =>
    cn(
        'inline-flex h-5 items-center gap-1.5 rounded border px-1.5 font-mono text-[11px] leading-none font-medium whitespace-nowrap',
        tones[props.tone],
        props.class,
    ),
);
</script>

<template>
    <span :class="classes">
        <span v-if="dot" class="size-1.5 rounded-full" :class="dots[tone]" />
        <slot />
    </span>
</template>
