<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';
import type { Option } from '@/types/cmdb';

// <select> natif stylé : accessible, léger, et accepte la valeur vide (= null)
// Les attributs (aria-label, name…) vont sur le <select>, pas sur le conteneur.
defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        options: Option[];
        placeholder?: string;
        class?: string;
        id?: string;
        invalid?: boolean;
        allowEmpty?: boolean;
    }>(),
    { placeholder: '—', allowEmpty: true },
);
const model = defineModel<string | number | null | undefined>();

function onChange(e: Event) {
    const v = (e.target as HTMLSelectElement).value;
    model.value = v === '' ? null : v;
}
</script>

<template>
    <div :class="cn('relative', props.class)">
        <select
            v-bind="$attrs"
            :id="id"
            :value="model ?? ''"
            :aria-invalid="invalid || undefined"
            class="h-9 w-full appearance-none rounded-md border border-input bg-transparent py-1 pr-8 pl-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30 [&>option]:bg-popover [&>option]:text-popover-foreground"
            @change="onChange"
        >
            <option v-if="allowEmpty" value="">{{ placeholder }}</option>
            <option v-for="o in options" :key="o.value" :value="o.value">
                {{ o.label }}
            </option>
        </select>
        <ChevronDown
            class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
        />
    </div>
</template>
