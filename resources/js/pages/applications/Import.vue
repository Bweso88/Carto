<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import Panel from '@/components/cmdb/Panel.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import type { Tone } from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import applications from '@/routes/applications';

type Action = 'create' | 'update' | 'unchanged' | 'error';
type PlanRow = {
    line: number | null;
    name: string | null;
    action: Action;
    auto: boolean;
    data: {
        type: string;
        servers: string[];
        technologies: { category: string; name: string }[];
        domains: string[];
        user_base: string | null;
        dependencies: { label: string; target: string }[];
    } | null;
    changes: { field: string; before: string | null; after: string }[];
    warnings: string[];
    errors: string[];
};
type Plan = {
    replace: boolean;
    existing: {
        applications: number;
        deployments: number;
        flows: number;
    } | null;
    new_domains: string[];
    missing_servers: Record<string, number>;
    columns: Record<string, string>;
    unknown_columns: string[];
    rows: PlanRow[];
    summary: Record<Action, number>;
};

const props = defineProps<{
    pending: { token: string; file: string; plan: Plan } | null;
    canReplace: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applications', href: applications.index() },
            { title: 'Importer', href: applications.import() },
        ],
    },
});

// ---------------------------------------------------------------- Étape 1 : dépôt du fichier
const form = useForm<{ file: File | null; replace: boolean }>({
    file: null,
    replace: false,
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
    form.post(applications.import.preview().url, { forceFormData: true });
}

// ---------------------------------------------------------------- Étape 2 : aperçu et confirmation
const labels: Record<Action, string> = {
    create: 'Création',
    update: 'Mise à jour',
    unchanged: 'Inchangée',
    error: 'Erreur',
};
const tones: Record<Action, Tone> = {
    create: 'success',
    update: 'info',
    unchanged: 'neutral',
    error: 'danger',
};
const typeLabels: Record<string, string> = {
    business: 'Métier',
    technical: 'Service technique',
    saas: 'SaaS',
    database: 'Base de données',
};

const filter = ref<'all' | 'warnings' | 'errors'>('all');
const rows = computed(() => {
    const all = props.pending?.plan.rows ?? [];

    return {
        all,
        warnings: all.filter((r) => r.warnings.length),
        errors: all.filter((r) => r.action === 'error'),
    }[filter.value];
});
const toApply = computed(() => {
    const s = props.pending?.plan.summary;

    return s ? s.create + s.update : 0;
});
const warningCount = computed(
    () => props.pending?.plan.rows.filter((r) => r.warnings.length).length ?? 0,
);
const missingServers = computed(() =>
    Object.entries(props.pending?.plan.missing_servers ?? {}),
);

const confirming = ref(false);
function confirm() {
    const plan = props.pending?.plan;
    if (
        plan?.replace &&
        !window.confirm(
            `Supprimer définitivement les ${plan.existing?.applications ?? 0} application(s) actuelles avant l'import ?`,
        )
    ) {
        return;
    }
    router.post(
        applications.import.store().url,
        { token: props.pending?.token },
        {
            onStart: () => (confirming.value = true),
            onFinish: () => (confirming.value = false),
        },
    );
}
function cancel() {
    router.delete(applications.import.cancel().url);
}
</script>

<template>
    <Head title="Importer des applications" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            eyebrow="Catalogue CMDB"
            title="Importer des applications"
            description="Depuis un fichier Excel (.xlsx, .xls exporté de Kerberos) ou CSV. Un aperçu est affiché avant tout enregistrement."
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="sm"
                    as="a"
                    :href="applications.import.template().url"
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
                            >Excel (.xlsx, .xls) ou CSV · 5 Mo maximum</span
                        >
                        <input
                            id="file"
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="sr-only"
                            @change="pick"
                        />
                    </label>
                    <p v-if="form.errors.file" class="text-sm text-destructive">
                        {{ form.errors.file }}
                    </p>

                    <label
                        v-if="canReplace"
                        class="flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors"
                        :class="
                            form.replace
                                ? 'border-destructive bg-destructive/5'
                                : 'hover:bg-muted/40'
                        "
                    >
                        <input
                            v-model="form.replace"
                            type="checkbox"
                            class="mt-0.5 size-4 accent-[var(--color-destructive)]"
                        />
                        <span class="grid gap-0.5 text-sm">
                            <span class="flex items-center gap-1.5 font-medium"
                                ><Trash2 class="size-4 text-destructive" />
                                Vider les applications avant l'import</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >Toutes les applications actuelles, leurs
                                déploiements et leurs dépendances seront
                                supprimés définitivement. Les serveurs ne sont
                                pas touchés.</span
                            >
                        </span>
                    </label>

                    <div class="flex justify-end">
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
                        Première ligne = en-têtes, dans n'importe quel ordre.
                        Seule la colonne <strong>Application</strong> est
                        obligatoire. C'est le format de l'export Kerberos.
                    </p>
                    <ul class="space-y-1.5">
                        <li>
                            <span class="font-mono text-xs">Serveur</span>
                            <span class="text-muted-foreground"
                                >: doit déjà exister dans MeriMap</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">Dépendance</span>
                            <span class="text-muted-foreground"
                                >: application dont elle dépend</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs">Utilisateur</span>
                            <span class="text-muted-foreground"
                                >: directions (« CLM/CFD », « ALL »)</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs"
                                >BDD, Langage, App/Service</span
                            >
                            <span class="text-muted-foreground"
                                >: technologies</span
                            >
                        </li>
                        <li>
                            <span class="font-mono text-xs"
                                >Éditeur, Activité, Interaction</span
                            >
                            <span class="text-muted-foreground"
                                >: facultatifs</span
                            >
                        </li>
                    </ul>
                    <p
                        class="rounded border bg-muted/40 p-2 text-xs text-muted-foreground"
                    >
                        Plusieurs lignes avec le même nom (ex. « Exchange » sur
                        trois serveurs) donnent une seule application déployée
                        sur ces serveurs.
                    </p>
                </div>
            </Panel>
        </div>

        <!-- Étape 2 -->
        <template v-else>
            <div
                v-if="pending.plan.replace && pending.plan.existing"
                class="flex items-start gap-3 rounded-md border border-destructive/50 bg-destructive/5 p-3 text-sm"
            >
                <Trash2 class="mt-0.5 size-4 shrink-0 text-destructive" />
                <p>
                    <strong>Mode remplacement :</strong>
                    {{ pending.plan.existing.applications }} application(s),
                    {{ pending.plan.existing.deployments }} déploiement(s) et
                    {{ pending.plan.existing.flows }} dépendance(s) seront
                    supprimés définitivement avant l'import. Les codes
                    recommenceront à APP-0001.
                </p>
            </div>

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
                <div class="mt-3 grid gap-2 text-xs text-muted-foreground">
                    <div class="flex flex-wrap items-center gap-1.5">
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
                    <div
                        v-if="pending.plan.new_domains.length"
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        Domaines métier à créer :
                        <ToneBadge
                            v-for="d in pending.plan.new_domains"
                            :key="d"
                            tone="info"
                            >{{ d }}</ToneBadge
                        >
                    </div>
                    <div
                        v-if="missingServers.length"
                        class="flex flex-wrap items-center gap-1.5"
                    >
                        Serveurs absents de MeriMap (pas de déploiement) :
                        <ToneBadge
                            v-for="[s, n] in missingServers"
                            :key="s"
                            tone="warning"
                            >{{ s }}{{ n > 1 ? ` ×${n}` : '' }}</ToneBadge
                        >
                    </div>
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
                <div class="max-h-[600px] overflow-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="sticky top-0 border-b bg-muted font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                        >
                            <tr class="text-left">
                                <th class="w-14 px-3 py-2 font-medium">
                                    Ligne
                                </th>
                                <th class="px-3 py-2 font-medium">
                                    Application
                                </th>
                                <th class="px-3 py-2 font-medium">Action</th>
                                <th class="px-3 py-2 font-medium">Détail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="(r, i) in rows"
                                :key="`${r.line}-${i}`"
                                class="align-top"
                            >
                                <td
                                    class="px-3 py-2 font-mono text-xs text-muted-foreground"
                                >
                                    {{ r.line ?? '—' }}
                                </td>
                                <td class="px-3 py-2 font-medium">
                                    {{ r.name ?? '—' }}
                                    <span
                                        v-if="r.data"
                                        class="block text-xs font-normal text-muted-foreground"
                                        >{{ typeLabels[r.data.type] }}</span
                                    >
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
                                            v-for="s in r.data.servers"
                                            :key="s"
                                            tone="accent"
                                            class="font-mono"
                                            >{{ s }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-for="t in r.data.technologies"
                                            :key="t.name"
                                            tone="neutral"
                                            >{{ t.name }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-for="d in r.data.domains"
                                            :key="d"
                                            tone="info"
                                            >{{ d }}</ToneBadge
                                        >
                                        <ToneBadge
                                            v-if="
                                                r.data.user_base &&
                                                !r.data.domains.length
                                            "
                                            tone="info"
                                            >{{ r.data.user_base }}</ToneBadge
                                        >
                                    </div>
                                    <p
                                        v-for="dep in r.data?.dependencies ??
                                        []"
                                        :key="dep.target"
                                        class="flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        Dépend de
                                        <ArrowRight class="size-3" />
                                        <span
                                            class="font-medium text-foreground"
                                            >{{ dep.target }}</span
                                        >
                                    </p>
                                    <p
                                        v-for="c in r.changes"
                                        :key="c.field"
                                        class="flex flex-wrap items-center gap-1.5 text-xs"
                                    >
                                        <span class="font-medium">{{
                                            c.field
                                        }}</span>
                                        <span
                                            v-if="c.before"
                                            class="text-muted-foreground line-through"
                                            >{{ c.before }}</span
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
                    Les lignes en erreur seront ignorées.
                </p>
                <Button variant="outline" @click="cancel">Annuler</Button>
                <Button
                    :variant="pending.plan.replace ? 'destructive' : 'default'"
                    :disabled="!toApply || confirming"
                    @click="confirm"
                >
                    <CheckCircle2 />
                    {{
                        !toApply
                            ? 'Rien à importer'
                            : pending.plan.replace
                              ? `Vider puis importer ${toApply} application(s)`
                              : `Importer ${toApply} application(s)`
                    }}
                </Button>
            </div>
        </template>

        <p class="text-xs text-muted-foreground">
            Astuce : l'import est aussi disponible en ligne de commande :
            <code class="font-mono"
                >php artisan merimap:import-applications fichier.xls
                --dry-run</code
            >.
            <Link
                :href="applications.index()"
                class="text-primary hover:underline"
                >Retour à la liste des applications</Link
            >
        </p>
    </div>
</template>
