<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = defineProps<{
    title?: string;
    description?: string;
    class?: string;
    bodyClass?: string;
}>();
</script>

<template>
    <section
        :class="
            cn(
                'overflow-hidden rounded-md border bg-card text-card-foreground',
                props.class,
            )
        "
    >
        <header
            v-if="title || $slots.actions"
            class="flex min-h-11 items-center justify-between gap-3 border-b px-4 py-2"
        >
            <div class="min-w-0">
                <h2 class="truncate text-[15px] font-semibold">{{ title }}</h2>
                <p v-if="description" class="text-xs text-muted-foreground">
                    {{ description }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div :class="cn('p-4', bodyClass)"><slot /></div>
    </section>
</template>
