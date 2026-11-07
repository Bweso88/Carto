<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types/cmdb';

defineProps<{ page: Paginated<unknown>; noun?: string }>();

function label(raw: string): string {
    if (
        raw.includes('Previous') ||
        raw.includes('Précédent') ||
        raw.includes('&laquo;')
    )
        return '‹';
    if (
        raw.includes('Next') ||
        raw.includes('Suivant') ||
        raw.includes('&raquo;')
    )
        return '›';

    return raw;
}
</script>

<template>
    <div
        class="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 sm:flex-row"
    >
        <p class="font-mono text-[11px] text-muted-foreground">
            <template v-if="page.total"
                >{{ page.from }}–{{ page.to }} sur {{ page.total }}
                {{ noun ?? 'éléments' }}</template
            >
            <template v-else>Aucun résultat</template>
        </p>
        <nav
            v-if="page.last_page > 1"
            class="flex flex-wrap items-center gap-1"
            aria-label="Pagination"
        >
            <template v-for="(link, i) in page.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex h-7 min-w-7 items-center justify-center rounded px-2 font-mono text-xs transition-colors"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    >{{ label(link.label) }}</Link
                >
                <span
                    v-else
                    class="inline-flex h-7 min-w-7 items-center justify-center px-2 font-mono text-xs text-muted-foreground/50"
                    >{{ label(link.label) }}</span
                >
            </template>
        </nav>
    </div>
</template>
