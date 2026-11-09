<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import type { Tone } from '@/components/cmdb/ToneBadge.vue';
import { toneOf } from '@/components/cmdb/tones';
import { Button } from '@/components/ui/button';
import servers from '@/routes/servers';
import type { Option } from '@/types/cmdb';

type Action = 'create' | 'update' | 'restore' | 'unchanged' | 'error';
type PlanRow = {
    line: number;
    hostname: string | null;
    action: Action;
    data?: {
        ips: string[];
        os: string | null;
        kind: string | null;
        site: string | null;
        criticality: string | null;
        environment: string | null;
        host: string | null;
        description: string | null;
    };
    changes: { field: string; before: string | null; after: string }[];
    warnings: string[];
    errors: string[];
};
type Plan = {
    columns: Record<string, string>;
    unknown_columns: string[];
    rows: PlanRow[];
    summary: Record<Action, number>;
};

const props = defineProps<{
    pending: {
        token: string;
        file: string;
        environment: string;
        plan: Plan;
    } | null;
    options: { environments: Option[] };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Serveurs', href: servers.index() },
            { title: 'Importer', href: servers.import() },
        ],
    },
});

// ---------------------------------------------------------------- Étape 1 : dépôt du fichier
const form = useForm<{ file: File | null; environment: string }>({
    file: null,
    environment: 'PROD',
});
const dragging = ref(false);

function pick(e: Event) {
    form.file = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function drop(e: DragEvent) {
    dragging.value = false;
    form.file = e.dataTransfer?.files?.[0] ?? null;
}
function analyse() {
    form.post(servers.import.preview().url, { forceFormData: true });
}

// ---------------------------------------------------------------- Étape 2 : aperçu et confirmation
const labels: Record<Action, string> = {
    create: 'Création',
    update: 'Mise à jour',
    restore: 'Restauration',
    unchanged: 'Inchangé',
    error: 'Erreur',
};
const tones: Record<Action, Tone> = {
    create: 'success',
    update: 'info',
    restore: 'accent',
    unchanged: 'neutral',
    error: 'danger',
};
const kindLabels: Record<string, string> = {
    physical: 'Physique',
    virtual: 'Virtuel',
    cloud: 'Cloud',
    container: 'Conteneur',
};
const criticalityLabels: Record<string, string> = {
    critical: 'Tier 1 · Vital',
    high: 'Tier 2 · Majeur',
    medium: 'Tier 3 · Modéré',
    low: 'Tier 4 · Faible',
};

const filter = ref<'all' | 'changes' | 'warnings' | 'errors'>('all');
const rows = computed(() => {
    const all = props.pending?.plan.rows ?? [];

    return {
        all,
        changes: all.filter((r) =>
            ['create', 'update', 'restore'].includes(r.action),
        ),
        warnings: all.filter((r) => r.warnings.length),
        errors: all.filter((r) => r.action === 'error'),
    }[filter.value];
});
const toApply = computed(() => {
    const s = props.pending?.plan.summary;

    return s ? s.create + s.update + s.restore : 0;
});
const warningCount = computed(
    () => props.pending?.plan.rows.filter((r) => r.warnings.length).length ?? 0,
);

const confirming = ref(false);
function confirm() {
    router.post(
        servers.import.store().url,
        { token: props.pending?.token },
        {
            onStart: () => (confirming.value = true),
            onFinish: () => (confirming.value = false),
        },
    );
}
function cancel() {
    router.delete(servers.import.cancel().url);
}
</script>

<template>
    <Head title="Importer des serveurs" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            eyebrow="Catalogue CMDB"
            title="Importer des serveurs"
            description="Depuis un fichier Excel (.xlsx) ou CSV. Un aperçu est affiché avant tout enregistrement."
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="servers.import.template().url"
                    ><Download /> Modèle de fichier</Button
                >
            </template>
        </PageHeader>

        <!-- Étape 1 -->
        <div v-if="!pending" class="grid gap-4 lg:grid-cols-3">
            <Panel title="1. Déposer le fichier" class="lg:col-span-2">
                <form class="grid gap-4" @submit.prevent="analyse">
                    <label
                        for="file"
                        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-md border-2 border-dashed px-6 py-10 text-center transition-colors"
                        :class="
                            dragging
                                ? 'border-primary bg-primary/5'
                                : form.errors.file
                                  ? 'border-destructive'
                                  : 'border-input hover:border-primary/60'
                        "
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="drop"
                    >
                        <FileSpreadsheet class="size-10 text-primary" />
                        <span v-if="form.file" class="font-medium">{{
                            form.file.name
                        }}</span>
                        <span v-else class="font-medium"
                            >Glissez votre fichier ici, ou cliquez pour le
                            choisir</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >Excel (.xlsx) ou CSV · 5 Mo maximum</span
                        >
                        <input
                            id="file"
                            type="file"
                            accept=".xlsx,.csv"
                            class="sr-only"
                            @change="pick"
                        />
                    </label>
                    <p v-if="form.errors.file" class="text-sm text-destructive">
                        {{ form.errors.file }}
                    </p>

                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <FormField
                            label="Environnement des nouveaux serveurs"
                            for="environment"
                            hint="Utilisé si le fichier n'a pas de colonne « Environnement »."
                            class="w-72"
                        >
                            <NativeSelect
                                id="environment"
                                v-model="form.environment"
                                :options="options.environments"
                                :allow-empty="false"
                            />
                        </FormField>
                        <Button
                            type="submit"
                            :disabled="!form.file || form.processing"
                            ><Upload /> Analyser le fichier</Button
                        >
                    </div>
                </form>
            </Panel>

            <Panel title="Format attendu">
                <div class="space-y-3 text-sm">
                    <p class="text-muted-foreground">
                        Première ligne = en-têtes. Seule la colonne
                        <strong>Serveur</strong> est obligatoire ; l'ordre des
                        colonnes est libre.
                    </p>
                    <ul class="space-y-1.5">
                        <li>
                            <span class="font-mono text-xs">Serveur</span>
                            <span class="text-muted-foreground"
                                >(ou Nom, Hostname)</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">IP</span>
                            <span class="text-muted-foreground"
                                >: une ou plusieurs</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">OS</span>
                            <span class="text-muted-foreground"
                                >: « WS 2012 R2 », « Linux Rhel9 »…</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">Type</span>
                            <span class="text-muted-foreground"
                                >: Physique, Virtuel, Cloud</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">Localisation</span>
                            <span class="text-muted-foreground">(ou Site)</span>
                        </li>
                        <li>
                            <span class="font-mono text-xs">Criticité</span>
                            <span class="text-muted-foreground"
                                >: Critique, À surveiller…</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs"
                                >Environnement, Hôte, Description</span
                            >
                            <span class="text-muted-foreground"
                                >: facultatifs</span
                            >
                        </li>
                    </ul>
                    <p
                        class="rounded border bg-muted/40 p-2 text-xs text-muted-foreground"
                    >
                        Un serveur déjà connu (même nom) est complété ; une
                        cellule vide n'efface jamais une valeur existante.
                        Relancer le même fichier ne crée pas de doublon.
                    </p>
                </div>
            </Panel>
        </div>

        <!-- Étape 2 -->
        <template v-else>
            <Panel
                :title="`2. Vérifier l'aperçu · ${pending.file}`"
                description="Rien n'a encore été enregistré."
            >
                <template #actions>
                    <Button variant="ghost" size="sm" @click="cancel"
                        ><X /> Changer de fichier</Button
                    >
                </template>
                <div class="flex flex-wrap items-center gap-2">
                    <ToneBadge
                        v-for="(n, action) in pending.plan.summary"
                        :key="action"
                        :tone="n ? tones[action] : 'neutral'"
                        class="h-6 px-2 text-xs"
                    >
                        {{ labels[action] }} : {{ n }}
                    </ToneBadge>
                    <ToneBadge
                        v-if="warningCount"
                        tone="warning"
                        class="h-6 px-2 text-xs"
                        ><AlertTriangle class="size-3" />
                        {{ warningCount }} avertissement(s)</ToneBadge
                    >
                </div>
                <div
                    class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                >
                    Colonnes reconnues :
                    <ToneBadge
                        v-for="(label, key) in pending.plan.columns"
                        :key="key"
                        tone="success"
                        >{{ label }}</ToneBadge
                    >
                    <template v-if="pending.plan.unknown_columns.length">
                        · ignorées :
                        <ToneBadge
                            v-for="c in pending.plan.unknown_columns"
                            :key="c"
                            tone="warning"
                            >{{ c }}</ToneBadge
                        >
                    </template>
                </div>
            </Panel>

            <div class="overflow-hidden rounded-md border bg-card">
                <div class="flex flex-wrap gap-1 border-b px-3 py-2">
                    <Button
                        v-for="f in [
                            {
                                key: 'all',
                                label: `Tout (${pending.plan.rows.length})`,
                            },
                            {
                                key: 'changes',
                                label: `Changements (${toApply})`,
                            },
                            {
                                key: 'warnings',
                                label: `Avertissements (${warningCount})`,
                            },
                            {
                                key: 'errors',
                                label: `Erreurs (${pending.plan.summary.error})`,
                            },
                        ] as const"
                        :key="f.key"
                        size="sm"
                        :variant="filter === f.key ? 'secondary' : 'ghost'"
                        @click="filter = f.key"
                        >{{ f.label }}</Button
                    >
                </div>
                <div class="max-h-[560px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="sticky top-0 border-b bg-muted font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                        >
                            <tr class="text-left">
                                <th class="w-14 px-3 py-2 font-medium">
                                    Ligne
                                </th>
                                <th class="px-3 py-2 font-medium">Serveur</th>
                                <th class="px-3 py-2 font-medium">Action</th>
                                <th class="px-3 py-2 font-medium">Détail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="r in rows"
                                :key="r.line"
                                class="align-top"
                            >
                                <td
                                    class="px-3 py-2 font-mono text-xs text-muted-foreground"
                                >
                                    {{ r.line }}
                                </td>
                                <td
                                    class="px-3 py-2 font-mono text-xs font-medium"
                                >
                                    {{ r.hostname ?? '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <ToneBadge :tone="tones[r.action]">{{
                                        labels[r.action]
                                    }}</ToneBadge>
                                </td>
                                <td class="space-y-1 px-3 py-2">
                                    <p
                                        v-for="e in r.errors"
                                        :key="e"
                                        class="text-xs text-destructive"
                                    >
                                        {{ e }}
                                    </p>
                                    <div
                                        v-if="r.action === 'create' && r.data"
                                        class="flex flex-wrap gap-1"
                                    >
                                        <ToneBadge
                                            v-for="ip in r.data.ips"
                                            :key="ip"
                                            tone="neutral"
                                            >{{ ip }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="r.data.os"
                                            tone="neutral"
                                            >{{ r.data.os }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="r.data.kind"
                                            tone="neutral"
                                            >{{
                                                kindLabels[r.data.kind]
                                            }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="r.data.site"
                                            tone="neutral"
                                            >{{ r.data.site }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="r.data.criticality"
                                            :tone="toneOf(r.data.criticality)"
                                            >{{
                                                criticalityLabels[
                                                    r.data.criticality
                                                ]
                                            }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="r.data.environment"
                                            tone="info"
                                            >{{ r.data.environment }}</ToneBadge
                                        >
                                    </div>
                                    <p
                                        v-for="c in r.changes"
                                        :key="c.field"
                                        class="flex flex-wrap items-center gap-1.5 text-xs"
                                    >
                                        <span class="font-medium">{{
                                            c.field
                                        }}</span>
                                        <span
                                            class="text-muted-foreground line-through"
                                            >{{ c.before ?? '∅' }}</span
                                        >
                                        <ArrowRight
                                            class="size-3 text-muted-foreground"
                                        />
                                        <span>{{ c.after }}</span>
                                    </p>
                                    <p
                                        v-for="w in r.warnings"
                                        :key="w"
                                        class="flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300"
                                    >
                                        <AlertTriangle
                                            class="size-3 shrink-0"
                                        />
                                        {{ w }}
                                    </p>
                                    <p
                                        v-if="r.action === 'unchanged'"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Déjà à jour.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-if="!rows.length"
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        Rien dans cette catégorie.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <p
                    v-if="pending.plan.summary.error"
                    class="mr-auto text-sm text-amber-700 dark:text-amber-300"
                >
                    Les lignes en erreur seront ignorées ; corrigez le fichier
                    et relancez l'import si besoin.
                </p>
                <Button variant="outline" @click="cancel">Annuler</Button>
                <Button :disabled="!toApply || confirming" @click="confirm">
                    <CheckCircle2 />
                    {{
                        toApply
                            ? `Importer ${toApply} serveur(s)`
                            : 'Rien à importer'
                    }}
                </Button>
            </div>
        </template>

        <p class="text-xs text-muted-foreground">
            Astuce : l'import est aussi disponible en ligne de commande :
            <code class="font-mono"
                >php artisan merimap:import-servers fichier.xlsx --dry-run</code
            >.
            <Link :href="servers.index()" class="text-primary hover:underline"
                >Retour à la liste des serveurs</Link
            >
        </p>
    </div>
</template>
