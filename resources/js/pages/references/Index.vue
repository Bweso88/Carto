<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDelete from '@/components/cmdb/ConfirmDelete.vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import PageHeader from '@/components/cmdb/PageHeader.vue';
import ToneBadge from '@/components/cmdb/ToneBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import references from '@/routes/references';
import type { Option } from '@/types/cmdb';

type Field = {
    name: string;
    label: string;
    type: 'text' | 'email' | 'url' | 'number' | 'date' | 'select' | 'boolean';
    options?: Option[];
    required?: boolean;
};
type RecordRow = {
    id: number;
    values: Record<string, string | number | boolean | null>;
    usage: number;
    obsolete?: boolean;
};

const props = defineProps<{
    type: string;
    types: { slug: string; title: string }[];
    definition: {
        title: string;
        singular: string;
        fields: Field[];
        columns: string[];
    };
    records: RecordRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Référentiels', href: references.index() }],
    },
});

const canEdit = computed(() => usePage().props.auth.can.editCmdb);
const fieldsByName = computed(() =>
    Object.fromEntries(props.definition.fields.map((f) => [f.name, f])),
);

// Formulaire partagé création / modification, ouvert dans une boîte de dialogue
const open = ref(false);
const editing = ref<RecordRow | null>(null);
const emptyValues = () =>
    Object.fromEntries(
        props.definition.fields.map((f) => [
            f.name,
            f.type === 'boolean' ? false : null,
        ]),
    );
const form =
    useForm<Record<string, string | number | boolean | null>>(emptyValues());

function openCreate() {
    editing.value = null;
    form.defaults(emptyValues());
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(row: RecordRow) {
    editing.value = row;
    form.defaults(
        Object.fromEntries(
            props.definition.fields.map((f) => [
                f.name,
                row.values[f.name] === null
                    ? f.type === 'boolean'
                        ? false
                        : null
                    : f.type === 'select'
                      ? String(row.values[f.name])
                      : row.values[f.name],
            ]),
        ),
    );
    form.reset();
    form.clearErrors();
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    if (editing.value) {
        form.put(
            references.update({ type: props.type, id: editing.value.id }).url,
            options,
        );
    } else {
        form.post(references.store(props.type).url, options);
    }
}

function display(row: RecordRow, column: string): string {
    const field = fieldsByName.value[column];
    const value = row.values[column];
    if (value === null || value === undefined || value === '') return '—';
    if (field?.type === 'boolean') return value ? 'Oui' : 'Non';
    if (field?.type === 'select')
        return (
            field.options?.find((o) => o.value === String(value))?.label ??
            String(value)
        );
    if (field?.type === 'date')
        return new Date(String(value)).toLocaleDateString('fr-FR');

    return String(value);
}
</script>

<template>
    <Head :title="`Référentiels · ${definition.title}`" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            eyebrow="Administration"
            title="Référentiels"
            description="Listes de valeurs utilisées dans la CMDB."
        >
            <template v-if="canEdit" #actions>
                <Button size="sm" @click="openCreate"
                    ><Plus /> Ajouter un(e) {{ definition.singular }}</Button
                >
            </template>
        </PageHeader>

        <nav class="flex flex-wrap gap-1 border-b" aria-label="Référentiels">
            <Link
                v-for="t in types"
                :key="t.slug"
                :href="references.index(t.slug)"
                class="-mb-px border-b-2 px-3 py-2 text-sm transition-colors"
                :class="
                    t.slug === type
                        ? 'border-primary font-medium text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                >{{ t.title }}</Link
            >
        </nav>

        <div class="overflow-hidden rounded-md border bg-card">
            <div class="overflow-x-auto">
                <table v-if="records.length" class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/50 font-mono text-[11px] tracking-wider text-muted-foreground uppercase"
                    >
                        <tr class="text-left">
                            <th
                                v-for="c in definition.columns"
                                :key="c"
                                class="px-4 py-2.5 font-medium"
                            >
                                {{ fieldsByName[c]?.label }}
                            </th>
                            <th class="px-4 py-2.5 text-right font-medium">
                                Utilisé
                            </th>
                            <th class="w-24" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="row in records"
                            :key="row.id"
                            class="hover:bg-muted/40"
                        >
                            <td
                                v-for="(c, i) in definition.columns"
                                :key="c"
                                class="px-4 py-2"
                                :class="i === 0 ? 'font-medium' : ''"
                            >
                                {{ display(row, c) }}
                                <ToneBadge
                                    v-if="i === 0 && row.obsolete"
                                    tone="danger"
                                    class="ml-1"
                                    >EOL</ToneBadge
                                >
                            </td>
                            <td
                                class="px-4 py-2 text-right font-mono text-xs tabular-nums"
                            >
                                {{ row.usage }}
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap">
                                <template v-if="canEdit">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`Modifier`"
                                        @click="openEdit(row)"
                                        ><Pencil class="text-muted-foreground"
                                    /></Button>
                                    <ConfirmDelete
                                        :href="
                                            references.destroy({
                                                type,
                                                id: row.id,
                                            }).url
                                        "
                                        :title="`Supprimer « ${display(row, definition.columns[0])} » ?`"
                                        :description="
                                            row.usage
                                                ? `Utilisé par ${row.usage} élément(s) : le lien sera vidé sur ces éléments.`
                                                : 'Cette valeur n\'est utilisée nulle part.'
                                        "
                                    >
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            aria-label="Supprimer"
                                            ><Trash2
                                                class="text-muted-foreground"
                                        /></Button>
                                    </ConfirmDelete>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="p-6 text-center text-sm text-muted-foreground">
                    Aucune valeur pour l'instant.
                </p>
            </div>
        </div>
    </div>

    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle
                        >{{ editing ? 'Modifier' : 'Ajouter' }} un(e)
                        {{ definition.singular }}</DialogTitle
                    >
                </DialogHeader>
                <div class="grid gap-3 sm:grid-cols-2">
                    <template v-for="f in definition.fields" :key="f.name">
                        <div
                            v-if="f.type === 'boolean'"
                            class="flex items-center gap-2 sm:col-span-2"
                        >
                            <Checkbox
                                :id="`ref-${f.name}`"
                                :model-value="Boolean(form[f.name])"
                                @update:model-value="
                                    form[f.name] = $event === true
                                "
                            />
                            <label :for="`ref-${f.name}`" class="text-sm">{{
                                f.label
                            }}</label>
                        </div>
                        <FormField
                            v-else
                            :label="f.label"
                            :for="`ref-${f.name}`"
                            :error="form.errors[f.name]"
                            :required="f.required"
                        >
                            <NativeSelect
                                v-if="f.type === 'select'"
                                :id="`ref-${f.name}`"
                                v-model="form[f.name] as string | null"
                                :options="f.options ?? []"
                                :allow-empty="!f.required"
                            />
                            <Input
                                v-else
                                :id="`ref-${f.name}`"
                                v-model="form[f.name] as string"
                                :type="f.type"
                            />
                        </FormField>
                    </template>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing"
                        >Enregistrer</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
