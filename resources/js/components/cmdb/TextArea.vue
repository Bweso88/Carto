<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: string;
    id?: string;
    rows?: number;
    placeholder?: string;
    invalid?: boolean;
}>();
const model = defineModel<string | null | undefined>();
</script>

<template>
    <textarea
        :id="id"
        v-model="model"
        :rows="rows ?? 3"
        :placeholder="placeholder"
        :aria-invalid="invalid || undefined"
        :class="
            cn(
                'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30',
                props.class,
            )
        "
    />
</template>
