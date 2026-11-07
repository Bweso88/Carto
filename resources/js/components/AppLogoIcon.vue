<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <BrandMark v-bind="$attrs" />
</template>
