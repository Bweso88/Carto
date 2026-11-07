<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    Flame,
    Network,
    Pencil,
    Server,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import AddDeploymentDialog from '@/components/cmdb/AddDeploymentDialog.vue';
import AddFlowDialog from '@/components/cmdb/AddFlowDialog.vue';
import ConfirmDelete from '@/components/cmdb/ConfirmDelete.vue';
import DataItem from '@/components/cmdb/DataItem.vue';
import EnumBadge from '@/components/cmdb/EnumBadge.vue';
import FlowList from '@/components/cmdb/FlowList.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { impact, topology } from '@/routes';
import applications from '@/routes/applications';
import deployments from '@/routes/deployments';
import servers from '@/routes/servers';
import type { Application, EnumOptions, Option } from '@/types/cmdb';

const props = defineProps<{
    application: Application;
    options: EnumOptions & {
        environments: Option[];
        servers: Option[];
        applications: Option[];
    };
}>();

defineOptions({
    layout: (p: { application: Application }) => ({
        breadcrumbs: [
            { title: 'Applications', href: applications.index() },
            {
                title: p.application.name,
                href: applications.show(p.application.id),
            },
        ],
    }),
});

const canEdit = computed(() => usePage().props.auth.can.editCmdb);
const app = computed(() => props.application);
const productionEnv = computed(
    () => props.options.environments[0]?.value ?? null,
);
const minutes = (m: number | null) =>
    m === null
        ? null
        : m >= 60
          ? `${Math.round((m / 60) * 10) / 10} h`
          : `${m} min`;
</script>

<template>
    <Head :title="app.name" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :eyebrow="`${app.code} · ${app.type?.label}`"
            :title="app.name"
            :description="app.description ?? undefined"
        >
            <template #meta>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <EnumBadge
                        :value="app.criticality"
                        empty="Criticité non évaluée"
                    />
                    <EnumBadge :value="app.lifecycle_status" dot />
                    <EnumBadge :value="app.data_classification" />
                    <ToneBadge v-if="app.handles_personal_data" tone="accent"
                        >Données personnelles</ToneBadge
                    >
                    <ToneBadge v-if="app.kerberos_id" tone="neutral"
                        >Kerberos #{{ app.kerberos_id }}</ToneBadge
                    >
                </div>
            </template>
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="topology({ query: { focus: app.id } })"
                        ><Network /> Topologie</Link
                    >
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="impact({ query: { 'applications[]': app.id } })"
                        ><Flame /> Simuler la panne</Link
                    >
                </Button>
                <Button v-if="canEdit" variant="outline" size="sm" as-child>
                    <Link :href="applications.edit(app.id)"
                        ><Pencil /> Modifier</Link
                    >
                </Button>
                <ConfirmDelete
                    v-if="canEdit"
                    :href="applications.destroy(app.id).url"
                    :title="`Supprimer ${app.name} ?`"
                    description="L'application sera archivée (suppression logique), ainsi que ses flux et déploiements dans les vues."
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
                    title="Hébergement"
                    :description="`${app.deployments?.length ?? 0} déploiement(s)`"
                    body-class="p-0"
                >
                    <template v-if="canEdit" #actions>
                        <AddDeploymentDialog
                            :application-id="app.id"
                            :servers="options.servers"
                            :roles="options.deploymentRoles"
                            :environments="options.environments"
                            :default-environment="productionEnv"
                        />
                    </template>
                    <table
                        v-if="app.deployments?.length"
                        class="w-full text-sm"
                    >
                        <thead
                            class="border-b bg-muted/50 font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                        >
                            <tr class="text-left">
                                <th class="px-4 py-2 font-medium">Serveur</th>
                                <th class="px-4 py-2 font-medium">Rôle</th>
                                <th class="px-4 py-2 font-medium">Système</th>
                                <th class="px-4 py-2 font-medium">Env.</th>
                                <th class="w-10" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr v-for="d in app.deployments" :key="d.id">
                                <td class="px-4 py-2">
                                    <Link
                                        :href="servers.show(d.server.id)"
                                        class="inline-flex items-center gap-2 font-mono text-xs font-medium hover:text-primary"
                                    >
                                        <Server
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        {{ d.server.hostname }}
                                    </Link>
                                    <div
                                        class="pl-5.5 font-mono text-[11px] text-muted-foreground"
                                    >
                                        {{ d.server.primary_ip }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ d.role?.label }}
                                    <div
                                        v-if="d.notes"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ d.notes }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm">
                                    {{ d.server.os?.label ?? '—' }}
                                    <ToneBadge
                                        v-if="d.server.os?.obsolete"
                                        tone="danger"
                                        class="ml-1"
                                        >EOL</ToneBadge
                                    >
                                </td>
                                <td class="px-4 py-2 font-mono text-xs">
                                    {{ d.environment?.code ?? '—' }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <ConfirmDelete
                                        v-if="canEdit"
                                        :href="deployments.destroy(d.id).url"
                                        :title="`Retirer ${d.server.hostname} ?`"
                                        description="Le serveur ne sera plus associé à cette application."
                                        confirm-label="Retirer"
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Retirer le déploiement"
                                            ><Trash2
                                                class="text-muted-foreground"
                                        /></Button>
                                    </ConfirmDelete>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-else
                        class="p-4 text-sm text-amber-700 dark:text-amber-300"
                    >
                        Aucun serveur déclaré : l'application n'apparaîtra pas
                        dans l'analyse d'impact.
                    </p>
                </Panel>

                <div class="grid gap-4 md:grid-cols-2">
                    <Panel
                        title="Dépendances (amont)"
                        description="Ce que l'application consomme"
                        body-class="p-0"
                    >
                        <template v-if="canEdit" #actions>
                            <AddFlowDialog
                                :application-id="app.id"
                                :application-name="app.name"
                                direction="outgoing"
                                :applications="options.applications"
                                :protocols="options.flowProtocols"
                            />
                        </template>
                        <FlowList
                            :items="app.outgoing_flows ?? []"
                            :can-edit="canEdit"
                            empty="Aucune dépendance déclarée."
                        />
                    </Panel>
                    <Panel
                        title="Consommateurs (aval)"
                        description="Qui dépend de l'application"
                        body-class="p-0"
                    >
                        <template v-if="canEdit" #actions>
                            <AddFlowDialog
                                :application-id="app.id"
                                :application-name="app.name"
                                direction="incoming"
                                :applications="options.applications"
                                :protocols="options.flowProtocols"
                            />
                        </template>
                        <FlowList
                            :items="app.incoming_flows ?? []"
                            :can-edit="canEdit"
                            empty="Aucune application ne dépend de celle-ci."
                        />
                    </Panel>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <Panel title="Gouvernance" body-class="py-1">
                    <dl>
                        <DataItem label="Domaines"
                            ><span
                                v-if="app.business_domains?.length"
                                class="inline-flex flex-wrap justify-end gap-1"
                                ><ToneBadge
                                    v-for="d in app.business_domains"
                                    :key="d.id"
                                    tone="neutral"
                                    >{{ d.name }}</ToneBadge
                                ></span
                            ></DataItem
                        >
                        <DataItem label="Éditeur">{{
                            app.vendor?.name
                        }}</DataItem>
                        <DataItem label="Resp. technique">
                            <template v-if="app.technical_owner">
                                {{ app.technical_owner.name }}
                                <div
                                    v-if="app.technical_owner.lead_name"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ app.technical_owner.lead_name }}
                                </div>
                                <div
                                    v-if="app.technical_owner.on_call_phone"
                                    class="font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ app.technical_owner.on_call_phone }}
                                </div>
                            </template>
                        </DataItem>
                        <DataItem label="Resp. métier">{{
                            app.business_owner?.name
                        }}</DataItem>
                        <DataItem label="Utilisateurs">
                            <template v-if="app.user_base || app.user_count">
                                {{ app.user_base
                                }}<span
                                    v-if="app.user_count"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    ·
                                    {{
                                        app.user_count.toLocaleString('fr-FR')
                                    }}</span
                                >
                            </template>
                        </DataItem>
                        <DataItem
                            v-if="app.documentation_url"
                            label="Documentation"
                        >
                            <a
                                :href="app.documentation_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                                >Ouvrir <ExternalLink class="size-3"
                            /></a>
                        </DataItem>
                    </dl>
                </Panel>

                <Panel title="Engagements de service">
                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded border bg-muted/40 p-2">
                            <div class="font-display text-lg font-semibold">
                                {{
                                    app.sla_target !== null
                                        ? `${app.sla_target}%`
                                        : '—'
                                }}
                            </div>
                            <div
                                class="font-mono text-[10px] text-muted-foreground uppercase"
                            >
                                SLA
                            </div>
                        </div>
                        <div class="rounded border bg-muted/40 p-2">
                            <div class="font-display text-lg font-semibold">
                                {{ minutes(app.rto_minutes) ?? '—' }}
                            </div>
                            <div
                                class="font-mono text-[10px] text-muted-foreground uppercase"
                            >
                                RTO
                            </div>
                        </div>
                        <div class="rounded border bg-muted/40 p-2">
                            <div class="font-display text-lg font-semibold">
                                {{ minutes(app.rpo_minutes) ?? '—' }}
                            </div>
                            <div
                                class="font-mono text-[10px] text-muted-foreground uppercase"
                            >
                                RPO
                            </div>
                        </div>
                    </div>
                </Panel>

                <Panel title="Pile technique" body-class="p-0">
                    <ul v-if="app.technologies?.length" class="divide-y">
                        <li
                            v-for="t in app.technologies"
                            :key="t.id"
                            class="flex items-center justify-between gap-2 px-4 py-2 text-sm"
                        >
                            <span
                                >{{ t.label }}
                                <span class="text-xs text-muted-foreground"
                                    >· {{ t.category?.label }}</span
                                ></span
                            >
                            <ToneBadge v-if="t.obsolete" tone="danger"
                                >EOL</ToneBadge
                            >
                        </li>
                    </ul>
                    <p v-else class="p-4 text-sm text-muted-foreground">
                        Non renseignée.
                    </p>
                </Panel>
            </div>
        </div>
    </div>
</template>
