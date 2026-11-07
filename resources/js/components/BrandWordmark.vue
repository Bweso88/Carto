<script setup lang="ts">
// Logotype « Meri » (marine / clair en mode sombre) + « Map » (orange)
withDefaults(defineProps<{ tagline?: boolean }>(), { tagline: false });
</script>

<template>
    <span class="inline-flex flex-col leading-none">
        <span class="font-display font-bold tracking-tight">
            <span class="text-brand-navy dark:text-foreground">Meri</span
            ><span class="text-brand-orange">Map</span>
        </span>
        <span
            v-if="tagline"
            class="mt-1 text-[0.42em] font-medium tracking-normal text-brand-navy/80 italic dark:text-muted-foreground"
        >
            Cartographie intelligente pour une gestion efficace
        </span>
    </span>
</template>
