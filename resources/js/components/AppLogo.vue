<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';
</script>

<template>
    <div
        class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-white p-0.5 ring-1 ring-black/5"
    >
        <BrandMark compact class="size-full" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span
            class="truncate font-display text-[15px] leading-tight font-bold tracking-tight"
        >
            <span class="text-brand-navy dark:text-foreground">Meri</span
            ><span class="text-brand-orange">Map</span>
        </span>
        <span
            class="truncate font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
            >Cartographie du SI</span
        >
    </div>
</template>
