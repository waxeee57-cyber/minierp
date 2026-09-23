<script setup>
import Badge from './Badge.vue';

defineProps({ status: String, label: String });
const tones = {
    pending: 'warning',
    paid: 'info',
    shipped: 'success',
    cancelled: 'neutral',
    validated: 'success',
    submitted: 'success',
    invalid: 'danger',
    storno_required: 'warning',
    imported: 'success',
    rejected: 'danger',
};
</script>

<template>
    <Badge :tone="tones[status] ?? 'neutral'" dot>{{ label }}</Badge>
</template>
