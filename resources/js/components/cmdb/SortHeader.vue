<script setup lang="ts">
import { ArrowDown, ArrowUp, ArrowUpDown } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    column: string;
    sort: string | null | undefined;
}>();
const emit = defineEmits<{ sort: [value: string] }>();

const state = computed(() =>
    props.sort === props.column
        ? 'asc'
        : props.sort === `-${props.column}`
          ? 'desc'
          : null,
);
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1 uppercase hover:text-foreground"
        @click="emit('sort', state === 'asc' ? `-${column}` : column)"
    >
        {{ label }}
        <ArrowUp v-if="state === 'asc'" class="size-3" />
        <ArrowDown v-else-if="state === 'desc'" class="size-3" />
        <ArrowUpDown v-else class="size-3 opacity-40" />
    </button>
</template>
