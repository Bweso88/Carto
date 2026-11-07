<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{ title: string; description?: string; icon?: LucideIcon }>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="size-8 text-muted-foreground/60"
        />
        <p class="font-medium">{{ title }}</p>
        <p v-if="description" class="max-w-sm text-sm text-muted-foreground">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
