<script setup>
defineProps({ rows: { type: Number, default: 6 } });
</script>

<template>
    <div class="divide-y divide-line" aria-busy="true" aria-label="Betöltés">
        <div v-for="i in rows" :key="i" class="flex items-center gap-4 px-4 py-3.5">
            <div class="skeleton size-8 rounded-full" />
            <div class="flex-1 space-y-2"><div class="skeleton h-3 w-2/5" /><div class="skeleton h-2.5 w-1/4" /></div>
            <div class="skeleton h-3 w-20" />
        </div>
    </div>
</template>
