<script setup lang="ts">
import { Check } from '@lucide/vue';
import type { Option } from '@/types/cmdb';

// Sélection multiple sous forme de pastilles cliquables (ex. domaines métier d'une application)
defineProps<{ options: Option[]; label: string; empty?: string }>();
const model = defineModel<number[]>({ required: true });

function toggle(value: number) {
    model.value = model.value.includes(value)
        ? model.value.filter((v) => v !== value)
        : [...model.value, value];
}
</script>

<template>
    <div role="group" :aria-label="label" class="flex flex-wrap gap-1.5">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            :aria-pressed="model.includes(Number(o.value))"
            class="inline-flex h-8 items-center gap-1.5 rounded-full border px-3 text-sm transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :class="
                model.includes(Number(o.value))
                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                    : 'border-input bg-transparent text-foreground hover:bg-accent'
            "
            @click="toggle(Number(o.value))"
        >
            <Check v-if="model.includes(Number(o.value))" class="size-3.5" />
            {{ o.label }}
        </button>
        <p v-if="!options.length" class="text-sm text-muted-foreground">
            {{ empty ?? 'Aucune valeur disponible.' }}
        </p>
    </div>
</template>
