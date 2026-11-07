<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import TextArea from '@/components/cmdb/TextArea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import servers from '@/routes/servers';
import type { EnumOptions, Option } from '@/types/cmdb';

type Values = Record<string, string | number | null | undefined>;

const props = defineProps<{
    server: { id: number; hostname: string } | null;
    values: Values;
    options: EnumOptions & {
        environments: Option[];
        sites: Option[];
        operatingSystems: Option[];
        teams: Option[];
        vendors: Option[];
        hosts: Option[];
    };
}>();

defineOptions({
    layout: (p: { server: { id: number; hostname: string } | null }) => ({
        breadcrumbs: [
            { title: 'Serveurs', href: servers.index() },
            p.server
                ? { title: p.server.hostname, href: servers.show(p.server.id) }
                : { title: 'Nouveau serveur', href: servers.create() },
        ],
    }),
});

const str = (v: unknown) => (v === null || v === undefined ? null : String(v));

const form = useForm({
    hostname: str(props.values.hostname) ?? '',
    fqdn: str(props.values.fqdn),
    kind: str(props.values.kind) ?? 'virtual',
    status: str(props.values.status) ?? 'active',
    criticality: str(props.values.criticality),
    environment_id: str(props.values.environment_id),
    site_id: str(props.values.site_id),
    os_id: str(props.values.os_id),
    owner_team_id: str(props.values.owner_team_id),
    host_id: str(props.values.host_id),
    manufacturer_id: str(props.values.manufacturer_id),
    model: str(props.values.model),
    serial_number: str(props.values.serial_number),
    cpu_cores: str(props.values.cpu_cores),
    ram_gb: str(props.values.ram_gb),
    storage_gb: str(props.values.storage_gb),
    commissioned_at: str(props.values.commissioned_at),
    warranty_end_at: str(props.values.warranty_end_at),
    description: str(props.values.description),
    ip_addresses_text: str(props.values.ip_addresses_text) ?? '',
});

function submit() {
    if (props.server) {
        form.put(servers.update(props.server.id).url, { preserveScroll: true });
    } else {
        form.post(servers.store().url, { preserveScroll: true });
    }
}

// Les erreurs de validation des IP arrivent sur ip_addresses.0, ip_addresses.1…
const ipError = () =>
    form.errors.ip_addresses_text ??
    Object.entries(form.errors).find(([k]) =>
        k.startsWith('ip_addresses'),
    )?.[1];
</script>

<template>
    <Head :title="server ? `Modifier ${server.hostname}` : 'Nouveau serveur'" />

    <form
        class="flex flex-1 flex-col gap-4 p-4 md:p-6"
        @submit.prevent="submit"
    >
        <PageHeader
            eyebrow="Catalogue CMDB"
            :title="server ? `Modifier ${server.hostname}` : 'Nouveau serveur'"
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            server ? servers.show(server.id) : servers.index()
                        "
                        >Annuler</Link
                    >
                </Button>
                <Button size="sm" type="submit" :disabled="form.processing"
                    ><Save /> Enregistrer</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <Panel title="Identification">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Nom d'hôte"
                            for="hostname"
                            :error="form.errors.hostname"
                            required
                        >
                            <Input
                                id="hostname"
                                v-model="form.hostname"
                                class="font-mono"
                                autocomplete="off"
                                v-focus="!server"
                                :aria-invalid="!!form.errors.hostname"
                            />
                        </FormField>
                        <FormField
                            label="FQDN"
                            for="fqdn"
                            :error="form.errors.fqdn"
                        >
                            <Input
                                id="fqdn"
                                v-model="form.fqdn"
                                class="font-mono"
                                placeholder="srv.domaine.local"
                            />
                        </FormField>
                        <FormField
                            label="Adresses IP"
                            for="ips"
                            :error="ipError()"
                            hint="Une par ligne. La première est l'adresse principale."
                            class="sm:row-span-2"
                        >
                            <TextArea
                                id="ips"
                                v-model="form.ip_addresses_text"
                                :rows="4"
                                class="font-mono"
                                placeholder="10.10.1.20"
                            />
                        </FormField>
                        <FormField
                            label="Système d'exploitation"
                            for="os"
                            :error="form.errors.os_id"
                        >
                            <NativeSelect
                                id="os"
                                v-model="form.os_id"
                                :options="options.operatingSystems"
                            />
                        </FormField>
                        <FormField
                            label="Hôte / hyperviseur"
                            for="host"
                            :error="form.errors.host_id"
                            hint="Pour une VM : le serveur physique qui l'héberge."
                        >
                            <NativeSelect
                                id="host"
                                v-model="form.host_id"
                                :options="options.hosts"
                            />
                        </FormField>
                    </div>
                </Panel>

                <Panel title="Matériel">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Constructeur"
                            for="manufacturer"
                            :error="form.errors.manufacturer_id"
                        >
                            <NativeSelect
                                id="manufacturer"
                                v-model="form.manufacturer_id"
                                :options="options.vendors"
                            />
                        </FormField>
                        <FormField
                            label="Modèle"
                            for="model"
                            :error="form.errors.model"
                            ><Input id="model" v-model="form.model"
                        /></FormField>
                        <FormField
                            label="N° de série"
                            for="serial"
                            :error="form.errors.serial_number"
                            ><Input
                                id="serial"
                                v-model="form.serial_number"
                                class="font-mono"
                        /></FormField>
                        <FormField
                            label="Cœurs CPU"
                            for="cpu"
                            :error="form.errors.cpu_cores"
                            ><Input
                                id="cpu"
                                v-model="form.cpu_cores"
                                type="number"
                                min="1"
                        /></FormField>
                        <FormField
                            label="Mémoire (Go)"
                            for="ram"
                            :error="form.errors.ram_gb"
                            ><Input
                                id="ram"
                                v-model="form.ram_gb"
                                type="number"
                                min="1"
                        /></FormField>
                        <FormField
                            label="Stockage (Go)"
                            for="storage"
                            :error="form.errors.storage_gb"
                            ><Input
                                id="storage"
                                v-model="form.storage_gb"
                                type="number"
                                min="1"
                        /></FormField>
                        <FormField
                            label="Mise en service"
                            for="commissioned"
                            :error="form.errors.commissioned_at"
                            ><Input
                                id="commissioned"
                                v-model="form.commissioned_at"
                                type="date"
                        /></FormField>
                        <FormField
                            label="Fin de garantie"
                            for="warranty"
                            :error="form.errors.warranty_end_at"
                            ><Input
                                id="warranty"
                                v-model="form.warranty_end_at"
                                type="date"
                        /></FormField>
                    </div>
                </Panel>

                <Panel title="Notes">
                    <TextArea
                        v-model="form.description"
                        :rows="4"
                        placeholder="Rôle du serveur, particularités, procédures…"
                    />
                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.description }}
                    </p>
                </Panel>
            </div>

            <div class="flex flex-col gap-4">
                <Panel title="Classification">
                    <div class="grid gap-4">
                        <FormField
                            label="Type"
                            for="kind"
                            :error="form.errors.kind"
                            required
                        >
                            <NativeSelect
                                id="kind"
                                v-model="form.kind"
                                :options="options.serverKinds"
                                :allow-empty="false"
                            />
                        </FormField>
                        <FormField
                            label="Statut"
                            for="status"
                            :error="form.errors.status"
                            required
                        >
                            <NativeSelect
                                id="status"
                                v-model="form.status"
                                :options="options.serverStatuses"
                                :allow-empty="false"
                            />
                        </FormField>
                        <FormField
                            label="Criticité"
                            for="criticality"
                            :error="form.errors.criticality"
                        >
                            <NativeSelect
                                id="criticality"
                                v-model="form.criticality"
                                :options="options.criticalities"
                                placeholder="Non évaluée"
                            />
                        </FormField>
                        <FormField
                            label="Environnement"
                            for="environment"
                            :error="form.errors.environment_id"
                        >
                            <NativeSelect
                                id="environment"
                                v-model="form.environment_id"
                                :options="options.environments"
                            />
                        </FormField>
                        <FormField
                            label="Site"
                            for="site"
                            :error="form.errors.site_id"
                        >
                            <NativeSelect
                                id="site"
                                v-model="form.site_id"
                                :options="options.sites"
                            />
                        </FormField>
                        <FormField
                            label="Équipe responsable"
                            for="team"
                            :error="form.errors.owner_team_id"
                        >
                            <NativeSelect
                                id="team"
                                v-model="form.owner_team_id"
                                :options="options.teams"
                            />
                        </FormField>
                    </div>
                </Panel>
            </div>
        </div>
    </form>
</template>
