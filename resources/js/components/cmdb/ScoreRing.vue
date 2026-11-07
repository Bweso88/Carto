<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ value: number }>();

const R = 42;
const C = 2 * Math.PI * R;
const color = computed(() =>
    props.value >= 70 ? '#ef4444' : props.value >= 35 ? '#f59e0b' : '#10b981',
);
const label = computed(() =>
    props.value >= 70
        ? 'Critique'
        : props.value >= 35
          ? 'Élevé'
          : props.value > 0
            ? 'Modéré'
            : 'Nul',
);
</script>

<template>
    <div class="flex items-center gap-4">
        <svg
            viewBox="0 0 100 100"
            class="size-24 -rotate-90"
            role="img"
            :aria-label="`Score d'impact ${value} sur 100`"
        >
            <circle
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                stroke="var(--border)"
                stroke-width="8"
            />
            <circle
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                :stroke="color"
                stroke-width="8"
                stroke-linecap="round"
                :stroke-dasharray="`${(value / 100) * C} ${C}`"
            />
            <text
                x="50"
                y="50"
                text-anchor="middle"
                dominant-baseline="central"
                transform="rotate(90 50 50)"
                class="fill-foreground font-display text-[26px] font-semibold"
            >
                {{ value }}
            </text>
        </svg>
        <div>
            <div
                class="font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
            >
                Score d'impact
            </div>
            <div class="text-lg font-semibold" :style="{ color }">
                {{ label }}
            </div>
            <div class="font-mono text-[11px] text-muted-foreground">
                / 100 points
            </div>
        </div>
    </div>
</template>
