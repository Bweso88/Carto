<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

defineProps<{
    label: string;
    for?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    class?: string;
}>();
</script>

<template>
    <div class="grid content-start gap-1.5" :class="$props.class">
        <Label
            :for="$props.for"
            class="text-xs font-medium text-muted-foreground"
        >
            {{ label }}<span v-if="required" class="text-destructive"> *</span>
        </Label>
        <slot />
        <p v-if="hint && !error" class="text-xs text-muted-foreground">
            {{ hint }}
        </p>
        <InputError :message="error" />
    </div>
</template>
