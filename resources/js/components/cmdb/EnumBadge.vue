<script setup lang="ts">
import type { EnumValue } from '@/types/cmdb';
import ToneBadge from './ToneBadge.vue';
import { toneOf } from './tones';

withDefaults(
    defineProps<{
        value: EnumValue | undefined;
        empty?: string;
        dot?: boolean;
    }>(),
    {
        empty: '',
        dot: false,
    },
);
</script>

<template>
    <ToneBadge v-if="value" :tone="toneOf(value)" :dot="dot">{{
        value.label
    }}</ToneBadge>
    <ToneBadge v-else-if="empty" tone="neutral">{{ empty }}</ToneBadge>
</template>
