<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AppWindow,
    Cpu,
    Flame,
    HardDrive,
    MemoryStick,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import ConfirmDelete from '@/components/cmdb/ConfirmDelete.vue';
import DataItem from '@/components/cmdb/DataItem.vue';
import EnumBadge from '@/components/cmdb/EnumBadge.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { impact } from '@/routes';
import applications from '@/routes/applications';
import servers from '@/routes/servers';
import type { EnumValue, Server } from '@/types/cmdb';

const props = defineProps<{
    server: Server;
    deployments: {
        id: number;
        role: EnumValue;
        environment: { code: string; name: string } | null;
        application: { id: number; code: string; name: string };
        criticality: EnumValue;
    }[];
}>();

defineOptions({
    layout: (p: { server: Server }) => ({
        breadcrumbs: [
            { title: 'Serveurs', href: servers.index() },
            { title: p.server.hostname, href: servers.show(p.server.id) },
        ],
    }),
});

const canEdit = computed(() => usePage().props.auth.can.editCmdb);
const date = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('fr-FR') : null;
</script>

<template>
    <Head :title="server.hostname" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :eyebrow="server.kind?.label ?? 'Serveur'"
            :title="server.hostname"
            :description="server.fqdn ?? undefined"
        >
            <template #meta>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <EnumBadge :value="server.status" dot />
                    <EnumBadge
                        :value="server.criticality"
                        empty="Criticité non évaluée"
                    />
                    <ToneBadge v-if="server.environment" tone="info">{{
                        server.environment.name
                    }}</ToneBadge>
                    <ToneBadge v-if="server.kerberos_id" tone="neutral"
                        >Kerberos #{{ server.kerberos_id }}</ToneBadge
                    >
                </div>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="impact({ query: { 'servers[]': server.id } })"
                        ><Flame /> Simuler la panne</Link
                    >
                </Button>
                <Button v-if="canEdit" variant="outline" size="sm" as-child>
                    <Link :href="servers.edit(server.id)"
                        ><Pencil /> Modifier</Link
                    >
                </Button>
                <ConfirmDelete
                    v-if="canEdit"
                    :href="servers.destroy(server.id).url"
                    :title="`Supprimer ${server.hostname} ?`"
                    description="Le serveur sera archivé (suppression logique). Ses déploiements restent en base mais ne sont plus affichés."
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        ><Trash2 /> Supprimer</Button
                    >
                </ConfirmDelete>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <Panel
                    title="Applications hébergées"
                    :description="`${deployments.length} déploiement(s)`"
                    body-class="p-0"
                >
                    <ul v-if="deployments.length" class="divide-y">
                        <li
                            v-for="d in deployments"
                            :key="d.id"
                            class="flex items-center justify-between gap-3 px-4 py-2.5"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <AppWindow
                                    class="size-4 shrink-0 text-primary"
                                />
                                <div class="min-w-0">
                                    <Link
                                        :href="
                                            applications.show(d.application.id)
                                        "
                                        class="truncate text-sm font-medium hover:text-primary"
                                        >{{ d.application.name }}</Link
                                    >
                                    <div
                                        class="font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ d.application.code }} ·
                                        {{ d.role?.label
                                        }}<template v-if="d.environment">
                                            · {{ d.environment.code }}</template
                                        >
                                    </div>
                                </div>
                            </div>
                            <EnumBadge :value="d.criticality" />
                        </li>
                    </ul>
                    <p v-else class="p-4 text-sm text-muted-foreground">
                        Aucune application déclarée sur ce serveur. Les
                        déploiements se gèrent depuis la fiche d'une
                        application.
                    </p>
                </Panel>

                <Panel
                    v-if="
                        server.kind?.value === 'physical' ||
                        server.guests?.length
                    "
                    title="Machines virtuelles hébergées"
                    :description="`${server.guests?.length ?? 0} VM`"
                    body-class="p-0"
                >
                    <template v-if="canEdit" #actions>
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    servers.create({
                                        query: { host_id: server.id },
                                    })
                                "
                                ><Plus /> Ajouter une VM</Link
                            >
                        </Button>
                    </template>
                    <ul
                        v-if="server.guests?.length"
                        class="grid divide-y sm:grid-cols-2 sm:divide-y-0"
                    >
                        <li
                            v-for="g in server.guests"
                            :key="g.id"
                            class="border-b px-4 py-2 sm:odd:border-r"
                        >
                            <Link
                                :href="servers.show(g.id)"
                                class="font-mono text-xs hover:text-primary"
                                >{{ g.hostname }}</Link
                            >
                        </li>
                    </ul>
                    <p v-else class="p-4 text-sm text-muted-foreground">
                        Aucune VM rattachée.
                    </p>
                </Panel>

                <Panel v-if="server.description" title="Notes">
                    <p class="text-sm whitespace-pre-line">
                        {{ server.description }}
                    </p>
                </Panel>
            </div>

            <div class="flex flex-col gap-4">
                <Panel title="Identification" body-class="py-1">
                    <dl>
                        <DataItem label="Adresses IP" mono>
                            <template v-if="server.ip_addresses?.length">
                                <div
                                    v-for="ip in server.ip_addresses"
                                    :key="ip.address"
                                >
                                    {{ ip.address
                                    }}<span
                                        v-if="ip.is_primary"
                                        class="ml-1 text-primary"
                                        >●</span
                                    >
                                </div>
                            </template>
                        </DataItem>
                        <DataItem label="Système">
                            <template v-if="server.os">
                                {{ server.os.label }}
                                <div
                                    v-if="
                                        server.os.end_of_life_at ||
                                        server.os.end_of_support_at
                                    "
                                    class="font-mono text-[11px]"
                                    :class="
                                        server.os.obsolete
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    fin
                                    {{
                                        date(
                                            server.os.end_of_life_at ??
                                                server.os.end_of_support_at,
                                        )
                                    }}
                                </div>
                            </template>
                        </DataItem>
                        <DataItem label="Hôte">
                            <Link
                                v-if="server.host"
                                :href="servers.show(server.host.id)"
                                class="font-mono text-xs hover:text-primary"
                                >{{ server.host.hostname }}</Link
                            >
                        </DataItem>
                        <DataItem label="Site">{{
                            server.site?.name
                        }}</DataItem>
                        <DataItem label="Équipe">{{
                            server.owner_team?.name
                        }}</DataItem>
                    </dl>
                </Panel>

                <Panel title="Matériel" body-class="py-1">
                    <div
                        v-if="
                            server.cpu_cores ||
                            server.ram_gb ||
                            server.storage_gb
                        "
                        class="grid grid-cols-3 gap-2 py-3"
                    >
                        <div class="rounded border bg-muted/40 p-2 text-center">
                            <Cpu
                                class="mx-auto mb-1 size-4 text-muted-foreground"
                            />
                            <div class="font-mono text-sm">
                                {{ server.cpu_cores ?? '—' }}
                            </div>
                            <div
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                cœurs
                            </div>
                        </div>
                        <div class="rounded border bg-muted/40 p-2 text-center">
                            <MemoryStick
                                class="mx-auto mb-1 size-4 text-muted-foreground"
                            />
                            <div class="font-mono text-sm">
                                {{ server.ram_gb ?? '—' }}
                            </div>
                            <div
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Go RAM
                            </div>
                        </div>
                        <div class="rounded border bg-muted/40 p-2 text-center">
                            <HardDrive
                                class="mx-auto mb-1 size-4 text-muted-foreground"
                            />
                            <div class="font-mono text-sm">
                                {{ server.storage_gb ?? '—' }}
                            </div>
                            <div
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Go disque
                            </div>
                        </div>
                    </div>
                    <dl>
                        <DataItem label="Constructeur">{{
                            server.manufacturer?.name
                        }}</DataItem>
                        <DataItem label="Modèle">{{ server.model }}</DataItem>
                        <DataItem label="N° de série" mono>{{
                            server.serial_number
                        }}</DataItem>
                        <DataItem label="Mise en service">{{
                            date(server.commissioned_at)
                        }}</DataItem>
                        <DataItem label="Fin de garantie">{{
                            date(server.warranty_end_at)
                        }}</DataItem>
                    </dl>
                </Panel>
            </div>
        </div>
    </div>
</template>
