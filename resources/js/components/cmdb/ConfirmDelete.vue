<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const props = defineProps<{
    href: string;
    title: string;
    description?: string;
    confirmLabel?: string;
}>();
const open = ref(false);
const processing = ref(false);

function confirm() {
    router.delete(props.href, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => {
            processing.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child><slot /></DialogTrigger>
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child
                    ><Button variant="outline">Annuler</Button></DialogClose
                >
                <Button
                    variant="destructive"
                    :disabled="processing"
                    @click="confirm"
                    >{{ confirmLabel ?? 'Supprimer' }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
