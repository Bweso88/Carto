<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Lock, LockOpen, Trash2 } from '@lucide/vue';
import ConfirmDelete from '@/components/cmdb/ConfirmDelete.vue';
import EnumBadge from '@/components/cmdb/EnumBadge.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import applications from '@/routes/applications';
import flows from '@/routes/flows';
import type { Flow } from '@/types/cmdb';

defineProps<{ items: Flow[]; canEdit: boolean; empty: string }>();
</script>

<template>
    <ul v-if="items.length" class="divide-y">
        <li
            v-for="f in items"
            :key="f.id"
            class="flex items-center justify-between gap-3 px-4 py-2.5"
        >
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <ArrowRight
                        class="size-3.5 shrink-0 text-muted-foreground"
                    />
                    <Link
                        v-if="f.application"
                        :href="applications.show(f.application.id)"
                        class="truncate text-sm font-medium hover:text-primary"
                        >{{ f.application.name }}</Link
                    >
                    <EnumBadge :value="f.criticality" />
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-1.5 pl-5">
                    <ToneBadge tone="info"
                        >{{ f.protocol?.label
                        }}<template v-if="f.port"
                            >:{{ f.port }}</template
                        ></ToneBadge
                    >
                    <ToneBadge v-if="f.is_encrypted === true" tone="success"
                        ><Lock class="size-3" /> chiffré</ToneBadge
                    >
                    <ToneBadge
                        v-else-if="f.is_encrypted === false"
                        tone="danger"
                        ><LockOpen class="size-3" /> en clair</ToneBadge
                    >
                    <ToneBadge v-if="!f.is_critical" tone="neutral"
                        >non critique</ToneBadge
                    >
                    <span
                        v-if="f.frequency"
                        class="font-mono text-[11px] text-muted-foreground"
                        >{{ f.frequency }}</span
                    >
                </div>
                <p
                    v-if="f.description"
                    class="mt-1 pl-5 text-xs text-muted-foreground"
                >
                    {{ f.description }}
                </p>
            </div>
            <ConfirmDelete
                v-if="canEdit"
                :href="flows.destroy(f.id).url"
                title="Supprimer ce flux ?"
                description="La dépendance ne sera plus prise en compte dans l'analyse d'impact."
            >
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Supprimer le flux"
                    ><Trash2 class="text-muted-foreground"
                /></Button>
            </ConfirmDelete>
        </li>
    </ul>
    <p v-else class="p-4 text-sm text-muted-foreground">{{ empty }}</p>
</template>
