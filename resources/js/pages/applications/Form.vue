<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { computed } from 'vue';
import ChipToggleGroup from '@/components/cmdb/ChipToggleGroup.vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import TextArea from '@/components/cmdb/TextArea.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import applications from '@/routes/applications';
import type { EnumOptions, Option } from '@/types/cmdb';

type AppRef = { id: number; code: string; name: string } | null;
type TechOption = Option & { category: string; obsolete: boolean };

const props = defineProps<{
    application: AppRef;
    values: Record<string, unknown>;
    options: EnumOptions & {
        businessDomains: Option[];
        vendors: Option[];
        teams: Option[];
        technologies: TechOption[];
    };
}>();

defineOptions({
    layout: (p: { application: AppRef }) => ({
        breadcrumbs: [
            { title: 'Applications', href: applications.index() },
            p.application
                ? {
                      title: p.application.name,
                      href: applications.show(p.application.id),
                  }
                : {
                      title: 'Nouvelle application',
                      href: applications.create(),
                  },
        ],
    }),
});

const str = (v: unknown) => (v === null || v === undefined ? null : String(v));
const initial = props.values;

const form = useForm({
    name: str(initial.name) ?? '',
    description: str(initial.description),
    type: str(initial.type) ?? 'business',
    lifecycle_status: str(initial.lifecycle_status) ?? 'production',
    criticality: str(initial.criticality),
    data_classification: str(initial.data_classification),
    handles_personal_data: Boolean(initial.handles_personal_data),
    business_domain_ids: ((initial.business_domain_ids as number[]) ?? []).map(
        Number,
    ),
    vendor_id: str(initial.vendor_id),
    business_owner_team_id: str(initial.business_owner_team_id),
    technical_owner_team_id: str(initial.technical_owner_team_id),
    user_base: str(initial.user_base),
    user_count: str(initial.user_count),
    sla_target: str(initial.sla_target),
    rto_minutes: str(initial.rto_minutes),
    rpo_minutes: str(initial.rpo_minutes),
    documentation_url: str(initial.documentation_url),
    technology_ids: ((initial.technology_ids as number[]) ?? []).map(Number),
});

// Technologies regroupées par catégorie (langages, moteurs BDD…)
const techGroups = computed(() => {
    const labels = Object.fromEntries(
        props.options.technologyCategories.map((c) => [c.value, c.label]),
    );
    const groups: Record<string, TechOption[]> = {};
    for (const t of props.options.technologies) {
        (groups[t.category] ??= []).push(t);
    }

    return Object.entries(groups).map(([cat, items]) => ({
        label: labels[cat] ?? cat,
        items,
    }));
});

function toggleTech(id: number, checked: boolean | 'indeterminate') {
    form.technology_ids =
        checked === true
            ? [...form.technology_ids, id]
            : form.technology_ids.filter((t) => t !== id);
}

// Les erreurs arrivent sur business_domain_ids.0, business_domain_ids.1…
const domainError = () =>
    Object.entries(form.errors).find(([k]) =>
        k.startsWith('business_domain_ids'),
    )?.[1];

function submit() {
    if (props.application) {
        form.put(applications.update(props.application.id).url, {
            preserveScroll: true,
        });
    } else {
        form.post(applications.store().url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head
        :title="
            application
                ? `Modifier ${application.name}`
                : 'Nouvelle application'
        "
    />

    <form
        class="flex flex-1 flex-col gap-4 p-4 md:p-6"
        @submit.prevent="submit"
    >
        <PageHeader
            :eyebrow="application?.code ?? 'Catalogue CMDB'"
            :title="
                application
                    ? `Modifier ${application.name}`
                    : 'Nouvelle application'
            "
        >
            <template #actions>
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            application
                                ? applications.show(application.id)
                                : applications.index()
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
                <Panel title="Description">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Nom"
                            for="name"
                            :error="form.errors.name"
                            required
                            class="sm:col-span-2"
                        >
                            <Input
                                id="name"
                                v-model="form.name"
                                v-focus="!application"
                                :aria-invalid="!!form.errors.name"
                            />
                        </FormField>
                        <FormField
                            label="Description"
                            for="description"
                            :error="form.errors.description"
                            class="sm:col-span-2"
                        >
                            <TextArea
                                id="description"
                                v-model="form.description"
                                :rows="3"
                                placeholder="Périmètre fonctionnel, usages principaux…"
                            />
                        </FormField>
                        <FormField
                            label="Domaines métier (directions utilisatrices)"
                            :error="domainError()"
                            hint="Une application peut servir plusieurs directions : sélectionnez-les toutes."
                            class="sm:col-span-2"
                        >
                            <ChipToggleGroup
                                v-model="form.business_domain_ids"
                                :options="options.businessDomains"
                                label="Domaines métier"
                                empty="Aucun domaine : ajoutez-en dans Référentiels → Domaines métier."
                            />
                        </FormField>
                        <FormField
                            label="Éditeur"
                            for="vendor"
                            :error="form.errors.vendor_id"
                        >
                            <NativeSelect
                                id="vendor"
                                v-model="form.vendor_id"
                                :options="options.vendors"
                            />
                        </FormField>
                        <FormField
                            label="Utilisateurs"
                            for="user_base"
                            :error="form.errors.user_base"
                            hint="Qui utilise l'application ?"
                        >
                            <Input
                                id="user_base"
                                v-model="form.user_base"
                                placeholder="Service RH, clients web…"
                            />
                        </FormField>
                        <FormField
                            label="Nombre d'utilisateurs"
                            for="user_count"
                            :error="form.errors.user_count"
                        >
                            <Input
                                id="user_count"
                                v-model="form.user_count"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            label="Documentation"
                            for="doc"
                            :error="form.errors.documentation_url"
                            class="sm:col-span-2"
                        >
                            <Input
                                id="doc"
                                v-model="form.documentation_url"
                                type="url"
                                placeholder="Lien vers le wiki, la doc éditeur…"
                            />
                        </FormField>
                    </div>
                </Panel>

                <Panel
                    title="Pile technique"
                    description="Langages, frameworks et moteurs de base de données utilisés"
                >
                    <div
                        v-if="techGroups.length"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <div
                            v-for="g in techGroups"
                            :key="g.label"
                            class="space-y-2"
                            role="group"
                            :aria-label="g.label"
                        >
                            <p
                                class="mb-1 font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                {{ g.label }}
                            </p>
                            <div
                                v-for="t in g.items"
                                :key="t.value"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`tech-${t.value}`"
                                    :model-value="
                                        form.technology_ids.includes(
                                            Number(t.value),
                                        )
                                    "
                                    @update:model-value="
                                        toggleTech(Number(t.value), $event)
                                    "
                                />
                                <Label
                                    :for="`tech-${t.value}`"
                                    class="text-sm font-normal"
                                    >{{ t.label }}</Label
                                >
                                <ToneBadge v-if="t.obsolete" tone="danger"
                                    >EOL</ToneBadge
                                >
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        Aucune technologie au référentiel.
                    </p>
                </Panel>
            </div>

            <div class="flex flex-col gap-4">
                <Panel title="Classification">
                    <div class="grid gap-4">
                        <FormField
                            label="Type"
                            for="type"
                            :error="form.errors.type"
                            required
                        >
                            <NativeSelect
                                id="type"
                                v-model="form.type"
                                :options="options.applicationTypes"
                                :allow-empty="false"
                            />
                        </FormField>
                        <FormField
                            label="Cycle de vie"
                            for="lifecycle"
                            :error="form.errors.lifecycle_status"
                            required
                        >
                            <NativeSelect
                                id="lifecycle"
                                v-model="form.lifecycle_status"
                                :options="options.lifecycleStatuses"
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
                            label="Classification des données"
                            for="classification"
                            :error="form.errors.data_classification"
                        >
                            <NativeSelect
                                id="classification"
                                v-model="form.data_classification"
                                :options="options.dataClassifications"
                            />
                        </FormField>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                id="pii"
                                v-model="form.handles_personal_data"
                            />
                            <Label for="pii" class="text-sm font-normal"
                                >Traite des données personnelles (RGPD)</Label
                            >
                        </div>
                    </div>
                </Panel>

                <Panel title="Responsables">
                    <div class="grid gap-4">
                        <FormField
                            label="Responsable technique"
                            for="tech_owner"
                            :error="form.errors.technical_owner_team_id"
                        >
                            <NativeSelect
                                id="tech_owner"
                                v-model="form.technical_owner_team_id"
                                :options="options.teams"
                            />
                        </FormField>
                        <FormField
                            label="Responsable métier"
                            for="biz_owner"
                            :error="form.errors.business_owner_team_id"
                        >
                            <NativeSelect
                                id="biz_owner"
                                v-model="form.business_owner_team_id"
                                :options="options.teams"
                            />
                        </FormField>
                    </div>
                </Panel>

                <Panel title="Engagements de service">
                    <div class="grid grid-cols-3 gap-3">
                        <FormField
                            label="SLA (%)"
                            for="sla"
                            :error="form.errors.sla_target"
                        >
                            <Input
                                id="sla"
                                v-model="form.sla_target"
                                type="number"
                                step="0.001"
                                min="0"
                                max="100"
                                placeholder="99.9"
                            />
                        </FormField>
                        <FormField
                            label="RTO (min)"
                            for="rto"
                            :error="form.errors.rto_minutes"
                        >
                            <Input
                                id="rto"
                                v-model="form.rto_minutes"
                                type="number"
                                min="0"
                            />
                        </FormField>
                        <FormField
                            label="RPO (min)"
                            for="rpo"
                            :error="form.errors.rpo_minutes"
                        >
                            <Input
                                id="rpo"
                                v-model="form.rpo_minutes"
                                type="number"
                                min="0"
                            />
                        </FormField>
                    </div>
                </Panel>
            </div>
        </div>
    </form>
</template>
