<script setup>
defineProps({ status: String, label: String });
const styles = {
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    paid: 'bg-brand-50 text-brand-700 ring-brand-100',
    shipped: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-slate-100 text-slate-500 ring-slate-200',
};
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="styles[status]">{{ label }}</span>
</template>
