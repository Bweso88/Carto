<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <p
                v-if="eyebrow"
                class="font-mono text-[11px] font-medium tracking-wider text-primary uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1 class="truncate text-2xl font-semibold tracking-tight">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p
                v-if="description"
                class="max-w-3xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
            <slot name="meta" />
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
