<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import flows from '@/routes/flows';
import type { Option } from '@/types/cmdb';

const props = defineProps<{
    applicationId: number;
    applicationName: string;
    direction: 'outgoing' | 'incoming';
    applications: Option[];
    protocols: Option[];
}>();
const open = ref(false);

const encryptionOptions: Option[] = [
    { value: '1', label: 'Chiffré (TLS…)' },
    { value: '0', label: 'En clair' },
];

const form = useForm({
    direction: props.direction,
    other_application_id: null as string | null,
    protocol: 'rest',
    port: null as string | null,
    is_encrypted: null as string | null,
    is_critical: true,
    frequency: null as string | null,
    description: null as string | null,
});

const title = computed(() =>
    props.direction === 'outgoing'
        ? 'Ajouter une dépendance'
        : 'Ajouter une application consommatrice',
);
const sentence = computed(() =>
    props.direction === 'outgoing'
        ? `${props.applicationName} consomme l'application choisie : si elle tombe, ${props.applicationName} est impactée.`
        : `L'application choisie consomme ${props.applicationName} : elle est impactée si ${props.applicationName} tombe.`,
);

function submit() {
    form.transform((data) => ({
        ...data,
        is_encrypted:
            data.is_encrypted === null ? null : data.is_encrypted === '1',
    })).post(flows.store(props.applicationId).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm"><Plus /> Ajouter</Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-lg">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>{{ sentence }}</DialogDescription>
                </DialogHeader>
                <FormField
                    label="Application"
                    for="flow-app"
                    :error="form.errors.other_application_id"
                    required
                >
                    <NativeSelect
                        id="flow-app"
                        v-model="form.other_application_id"
                        :options="applications"
                        placeholder="Choisir une application…"
                    />
                </FormField>
                <div class="grid grid-cols-3 gap-3">
                    <FormField
                        label="Protocole"
                        for="flow-proto"
                        :error="form.errors.protocol"
                        required
                        class="col-span-2"
                    >
                        <NativeSelect
                            id="flow-proto"
                            v-model="form.protocol"
                            :options="protocols"
                            :allow-empty="false"
                        />
                    </FormField>
                    <FormField
                        label="Port"
                        for="flow-port"
                        :error="form.errors.port"
                    >
                        <Input
                            id="flow-port"
                            v-model="form.port"
                            type="number"
                            min="1"
                            max="65535"
                            class="font-mono"
                        />
                    </FormField>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <FormField
                        label="Chiffrement"
                        for="flow-enc"
                        :error="form.errors.is_encrypted"
                    >
                        <NativeSelect
                            id="flow-enc"
                            v-model="form.is_encrypted"
                            :options="encryptionOptions"
                            placeholder="Inconnu"
                        />
                    </FormField>
                    <FormField
                        label="Fréquence"
                        for="flow-freq"
                        :error="form.errors.frequency"
                    >
                        <Input
                            id="flow-freq"
                            v-model="form.frequency"
                            placeholder="Temps réel, batch nocturne…"
                        />
                    </FormField>
                </div>
                <div class="flex items-start gap-2">
                    <Checkbox
                        id="flow-critical"
                        v-model="form.is_critical"
                        class="mt-0.5"
                    />
                    <Label
                        for="flow-critical"
                        class="text-sm leading-snug font-normal"
                    >
                        Dépendance critique
                        <span class="block text-xs text-muted-foreground"
                            >Sans elle, l'application consommatrice ne
                            fonctionne plus (sinon : fonctionnement
                            dégradé).</span
                        >
                    </Label>
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || !form.other_application_id
                        "
                        >Ajouter le flux</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
