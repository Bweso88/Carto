<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import FormField from '@/components/cmdb/FormField.vue';
import NativeSelect from '@/components/cmdb/NativeSelect.vue';
import { Button } from '@/components/ui/button';
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
import deployments from '@/routes/deployments';
import type { Option } from '@/types/cmdb';

const props = defineProps<{
    applicationId: number;
    servers: Option[];
    roles: Option[];
    environments: Option[];
    defaultEnvironment?: string | null;
}>();
const open = ref(false);

const form = useForm({
    server_id: null as string | null,
    role: 'application',
    environment_id: props.defaultEnvironment ?? null,
    notes: null as string | null,
});

function submit() {
    form.post(deployments.store(props.applicationId).url, {
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
        <DialogContent class="sm:max-w-md">
            <form class="grid gap-4" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>Déployer sur un serveur</DialogTitle>
                    <DialogDescription
                        >Indiquez sur quel serveur tourne l'application, et avec
                        quel rôle.</DialogDescription
                    >
                </DialogHeader>
                <FormField
                    label="Serveur"
                    for="dep-server"
                    :error="form.errors.server_id"
                    required
                >
                    <NativeSelect
                        id="dep-server"
                        v-model="form.server_id"
                        :options="servers"
                        placeholder="Choisir un serveur…"
                    />
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField
                        label="Rôle"
                        for="dep-role"
                        :error="form.errors.role"
                        required
                    >
                        <NativeSelect
                            id="dep-role"
                            v-model="form.role"
                            :options="roles"
                            :allow-empty="false"
                        />
                    </FormField>
                    <FormField
                        label="Environnement"
                        for="dep-env"
                        :error="form.errors.environment_id"
                    >
                        <NativeSelect
                            id="dep-env"
                            v-model="form.environment_id"
                            :options="environments"
                        />
                    </FormField>
                </div>
                <FormField
                    label="Remarque"
                    for="dep-notes"
                    :error="form.errors.notes"
                >
                    <Input
                        id="dep-notes"
                        v-model="form.notes"
                        placeholder="Nœud actif, réplica…"
                    />
                </FormField>
                <DialogFooter>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.server_id"
                        >Ajouter le déploiement</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
