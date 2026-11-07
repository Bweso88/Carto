<template>
    <div
        class="flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[10px] text-muted-foreground"
    >
        <span class="flex items-center gap-1.5"
            ><span class="h-0.5 w-5 bg-(--chart-2)" /> Flux critique</span
        >
        <span class="flex items-center gap-1.5"
            ><span
                class="h-0 w-5 border-t-2 border-dashed border-(--chart-2)"
            />
            Flux non critique</span
        >
        <span class="flex items-center gap-1.5"
            ><span class="h-0.5 w-5 bg-red-600" /> Flux en clair</span
        >
        <span class="flex items-center gap-1.5"
            ><span class="h-px w-5 bg-muted-foreground" /> Déploiement</span
        >
        <span class="flex items-center gap-1.5"
            ><span
                class="h-0 w-5 border-t border-dotted border-muted-foreground"
            />
            Hébergement</span
        >
        <span class="flex items-center gap-1.5"
            ><span class="size-2 rounded-full bg-red-500" /> Tier 1</span
        >
        <span class="flex items-center gap-1.5"
            ><span class="size-2 rounded-full bg-amber-500" /> Tier 2</span
        >
    </div>
</template>
