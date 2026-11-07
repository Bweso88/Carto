<script setup lang="ts">
defineProps<{ label: string; mono?: boolean }>();
</script>

<template>
    <div
        class="flex items-start justify-between gap-4 border-b border-border/60 py-2 text-sm last:border-0"
    >
        <dt
            class="shrink-0 font-mono text-[11px] tracking-wide text-muted-foreground uppercase"
        >
            {{ label }}
        </dt>
        <!-- Valeur vide => tiret (via :empty, qui ignore commentaires et textes vides) -->
        <dd
            class="min-w-0 text-right empty:after:text-muted-foreground/60 empty:after:content-['—']"
            :class="mono ? 'font-mono text-xs' : ''"
        >
            <slot />
        </dd>
    </div>
</template>
